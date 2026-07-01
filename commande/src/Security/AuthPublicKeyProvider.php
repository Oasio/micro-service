<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Récupère AUTOMATIQUEMENT la/les clé(s) publique(s) RS256 du service Auth via son
 * endpoint JWKS (JWT_JWKS_URL), et les met en cache 1 h (S12.3 — étape 1).
 *
 * Aucun fichier de clé à gérer : tout vient du JWKS standard. Chaque clé JWK (n, e) est
 * convertie en PEM pour permettre la vérification de signature par OpenSSL.
 */
class AuthPublicKeyProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(JWT_JWKS_URL)%')]
        private readonly string $jwksUrl,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, string> clés publiques PEM indexées par `kid`
     */
    public function getPublicKeys(): array
    {
        return $this->cache->get('auth_jwks_public_keys', function (ItemInterface $item): array {
            $item->expiresAfter(3600); // cache 1 h (S12.4 — piège 3).

            $jwks = $this->httpClient->request('GET', $this->jwksUrl, ['timeout' => 3])->toArray();

            $pems = [];
            foreach ($jwks['keys'] ?? [] as $i => $jwk) {
                if (($jwk['kty'] ?? '') !== 'RSA') {
                    continue;
                }
                $pems[(string) ($jwk['kid'] ?? $i)] = JwkConverter::toPem($jwk);
            }

            if ([] === $pems) {
                throw new \RuntimeException('Aucune clé RSA exploitable dans la JWKS du service Auth.');
            }

            return $pems;
        });
    }
}
