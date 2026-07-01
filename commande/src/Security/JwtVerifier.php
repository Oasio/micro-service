<?php

namespace App\Security;

/**
 * Vérifie un JWT RS256 émis par le service Auth (S12.3 — étape 2) :
 *  - algorithme imposé RS256 (on REFUSE none / HS256 — S12.4 piège 6) ;
 *  - signature vérifiée avec la clé publique de l'Auth (récupérée via JWKS) ;
 *  - expiration (exp) toujours contrôlée.
 *
 * Renvoie les claims si tout est valide, sinon lève une \RuntimeException.
 */
class JwtVerifier
{
    public function __construct(
        private readonly AuthPublicKeyProvider $keyProvider,
    ) {
    }

    /**
     * @return array<string, mixed> claims du JWT (sub, username, roles, iat, exp…)
     */
    public function verify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (3 !== \count($parts)) {
            throw new \RuntimeException('JWT malformé.');
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(Base64Url::decode($headerB64), true);
        if (!\is_array($header) || ($header['alg'] ?? null) !== 'RS256') {
            throw new \RuntimeException('Algorithme non supporté (RS256 requis).');
        }

        // Sélection de la clé par kid (sinon première clé du JWKS).
        $keys = $this->keyProvider->getPublicKeys();
        $kid = $header['kid'] ?? null;
        $pem = (null !== $kid && isset($keys[$kid])) ? $keys[$kid] : reset($keys);
        if (!\is_string($pem) || '' === $pem) {
            throw new \RuntimeException('Aucune clé publique disponible pour vérifier le JWT.');
        }

        // Vérification de la signature RS256 (SHA-256) avec la clé publique de l'Auth.
        $ok = openssl_verify(
            $headerB64.'.'.$payloadB64,
            Base64Url::decode($signatureB64),
            $pem,
            OPENSSL_ALGO_SHA256,
        );
        if (1 !== $ok) {
            throw new \RuntimeException('Signature JWT invalide.');
        }

        $claims = json_decode(Base64Url::decode($payloadB64), true);
        if (!\is_array($claims)) {
            throw new \RuntimeException('Payload JWT illisible.');
        }

        // Expiration toujours contrôlée (S12.4 piège 6). Tolérance d'horloge de 30 s.
        if (isset($claims['exp']) && time() > ((int) $claims['exp'] + 30)) {
            throw new \RuntimeException('JWT expiré.');
        }

        return $claims;
    }
}
