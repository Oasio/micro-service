<?php

namespace App\Tests\Security;

use App\Security\Base64Url;
use App\Security\JwkConverter;
use App\Tests\Fake\TestKeys;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que la conversion JWK (n, e) → PEM produit une clé publique RSA valide,
 * identique à la clé d'origine (c'est ce qui alimente la vérification depuis la JWKS de l'Auth).
 */
class JwkConverterTest extends TestCase
{
    public function testJwkToPemReproducesOriginalPublicKey(): void
    {
        // 1. À partir d'une vraie clé publique (clé de test), on extrait n/e (format JWK).
        $details = openssl_pkey_get_details(openssl_pkey_get_public(TestKeys::PUBLIC_PEM));
        $this->assertNotFalse($details);

        $jwk = [
            'kty' => 'RSA',
            'n' => Base64Url::encode($details['rsa']['n']),
            'e' => Base64Url::encode($details['rsa']['e']),
        ];

        // 2. On reconvertit le JWK en PEM via notre convertisseur.
        $rebuiltPem = JwkConverter::toPem($jwk);

        // 3. La clé reconstruite doit être valide et identique à l'originale.
        $rebuilt = openssl_pkey_get_public($rebuiltPem);
        $this->assertNotFalse($rebuilt, 'Le PEM reconstruit doit être une clé publique valide.');

        $rebuiltDetails = openssl_pkey_get_details($rebuilt);
        $this->assertSame($details['rsa']['n'], $rebuiltDetails['rsa']['n']);
        $this->assertSame($details['rsa']['e'], $rebuiltDetails['rsa']['e']);
        $this->assertSame(
            preg_replace('/\s+/', '', TestKeys::PUBLIC_PEM),
            preg_replace('/\s+/', '', $rebuiltPem),
            'Le PEM reconstruit doit être identique à la clé publique originale.',
        );
    }
}
