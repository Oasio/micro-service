<?php

namespace App\Security;

/**
 * Convertit une clé publique RSA au format JWK (modulus `n` + exposant `e`, base64url)
 * en PEM (SubjectPublicKeyInfo), exploitable par openssl_verify.
 *
 * C'est l'opération `jwkToPem` mentionnée dans le contrat d'authentification : la clé
 * publique de l'Auth est exposée en JWKS (RFC 7517), on la transforme en PEM pour vérifier
 * la signature RS256 des JWT. Implémentation en PHP pur (encodage ASN.1 DER), sans dépendance.
 */
final class JwkConverter
{
    /**
     * @param array{kty?: string, n?: string, e?: string} $jwk
     */
    public static function toPem(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || !isset($jwk['n'], $jwk['e'])) {
            throw new \InvalidArgumentException('JWK invalide : clé RSA (n, e) attendue.');
        }

        $modulus = Base64Url::decode($jwk['n']);
        $exponent = Base64Url::decode($jwk['e']);

        // SEQUENCE { INTEGER modulus, INTEGER exponent }
        $rsaPublicKey = self::seq(self::int($modulus).self::int($exponent));

        // AlgorithmIdentifier { OID rsaEncryption (1.2.840.113549.1.1.1), NULL }
        $algorithm = self::seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"."\x05\x00");

        // SubjectPublicKeyInfo { AlgorithmIdentifier, BIT STRING { rsaPublicKey } }
        $spki = self::seq($algorithm.self::bitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    /** ASN.1 DER : encode la longueur (forme courte < 128, sinon forme longue). */
    private static function len(int $length): string
    {
        if ($length < 0x80) {
            return \chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return \chr(0x80 | \strlen($bytes)).$bytes;
    }

    /** SEQUENCE (tag 0x30). */
    private static function seq(string $content): string
    {
        return "\x30".self::len(\strlen($content)).$content;
    }

    /** INTEGER (tag 0x02) — préfixe 0x00 si le bit de poids fort est à 1 (entier non signé). */
    private static function int(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ('' === $bytes) {
            $bytes = "\x00";
        }
        if (\ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::len(\strlen($bytes)).$bytes;
    }

    /** BIT STRING (tag 0x03) — premier octet = nombre de bits de bourrage (0 ici). */
    private static function bitString(string $content): string
    {
        $content = "\x00".$content;

        return "\x03".self::len(\strlen($content)).$content;
    }
}
