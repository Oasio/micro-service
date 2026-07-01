<?php

namespace App\Security;

/**
 * Encodage/décodage base64url (RFC 7515) — utilisé par les JWT et le format JWKS.
 */
final class Base64Url
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode(string $data): string
    {
        $remainder = \strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return false === $decoded ? '' : $decoded;
    }
}
