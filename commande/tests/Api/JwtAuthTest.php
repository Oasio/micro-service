<?php

namespace App\Tests\Api;

use App\Security\AuthPublicKeyProvider;
use App\Tests\Fake\FakeAuthPublicKeyProvider;
use App\Tests\Fake\TestKeys;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests de la protection JWT des endpoints (S12.3 — étape 4 / checkpoint slide 67).
 *
 * Les routes /api/orders et /api/customers exigent un JWT RS256 valide → sinon 401.
 * /health reste public. Le provider de clé publique est remplacé par un double (clé de test).
 */
class JwtAuthTest extends WebTestCase
{
    private function client(): KernelBrowser
    {
        $client = static::createClient();
        static::getContainer()->set(AuthPublicKeyProvider::class, new FakeAuthPublicKeyProvider());

        return $client;
    }

    public function testProtectedEndpointWithoutTokenReturns401(): void
    {
        $client = $this->client();
        $client->request('GET', '/api/orders', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithBogusTokenReturns401(): void
    {
        $client = $this->client();
        $client->request('GET', '/api/orders', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer not.a.real.jwt',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithExpiredTokenReturns401(): void
    {
        $client = $this->client();
        $client->request('GET', '/api/orders', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer '.TestKeys::mintToken(['exp' => time() - 60]),
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithValidTokenReturns200(): void
    {
        $client = $this->client();
        $client->request('GET', '/api/orders', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer '.TestKeys::mintToken(),
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    public function testHealthEndpointIsPublic(): void
    {
        $client = $this->client();
        $client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
    }
}
