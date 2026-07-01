<?php

namespace App\Tests\Api;

use App\Security\AuthPublicKeyProvider;
use App\Service\CartClient;
use App\Service\CatalogClient;
use App\Service\PaymentClient;
use App\Tests\Fake\FakeAuthPublicKeyProvider;
use App\Tests\Fake\FakeCartClient;
use App\Tests\Fake\FakeCatalogClient;
use App\Tests\Fake\FakePaymentClient;
use App\Tests\Fake\TestKeys;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests d'API du service Commande (WebTestCase).
 *
 * Les 3 dépendances (Panier, Catalogue, Paiement) sont remplacées par des doubles
 * déterministes : on teste l'orchestration et les codes HTTP du contrat sans dépendre
 * des vrais services.
 */
class OrderApiTest extends WebTestCase
{
    private function makeClient(bool $paymentAccept = true): KernelBrowser
    {
        $client = static::createClient();
        // Sans cela, le kernel est rebooté entre deux requêtes et nos doubles seraient perdus.
        $client->disableReboot();

        $c = static::getContainer();
        $c->set(CartClient::class, new FakeCartClient());
        $c->set(CatalogClient::class, new FakeCatalogClient());
        $c->set(PaymentClient::class, new FakePaymentClient($paymentAccept));

        // Les endpoints /api/orders sont protégés par JWT : on remplace le provider de clé
        // publique par un double (clé de test) et on attache un jeton signé avec la clé privée
        // de test → la vérification JWKS native réussit, sans appeler le vrai service Auth.
        $c->set(AuthPublicKeyProvider::class, new FakeAuthPublicKeyProvider());
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.TestKeys::mintToken());

        return $client;
    }

    private function validPayload(string $customerId = 'CUST001', string $cartId = 'CART123'): array
    {
        return [
            'customerId' => $customerId,
            'cartId' => $cartId,
            'shippingAddress' => [
                'street' => '10 rue de Paris',
                'city' => 'Rennes',
                'postalCode' => '35000',
                'country' => 'France',
            ],
        ];
    }

    private function postOrder(KernelBrowser $client, array $payload, ?string $idempotencyKey = null): array
    {
        $server = [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ];
        if (null !== $idempotencyKey) {
            $server['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
        }

        $client->request('POST', '/api/orders', server: $server, content: json_encode($payload));

        return json_decode($client->getResponse()->getContent() ?: '[]', true) ?? [];
    }

    public function testListOrdersRespondsOk(): void
    {
        $client = $this->makeClient();
        $client->request('GET', '/api/orders', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testCreateOrderReturns201AndIsPaid(): void
    {
        $client = $this->makeClient();
        $data = $this->postOrder($client, $this->validPayload());

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('PAID', $data['status']);
        $this->assertStringStartsWith('ORD-', $data['orderId']);
        $this->assertSame('CUST001', $data['customerId']);
        // 49,95 € × 2 + 19,99 € × 1 = 119,89 € (prix OFFICIELS du Catalogue, pas du client).
        $this->assertSame(119.89, $data['totalAmount']);
        $this->assertCount(2, $data['items']);
        $this->assertSame('Rennes', $data['shippingAddress']['city']);
    }

    public function testCreateOrderWithUnknownCartReturns422(): void
    {
        $client = $this->makeClient();
        $this->postOrder($client, $this->validPayload(cartId: 'CART_UNKNOWN'));

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateOrderWithMissingCustomerReturns422(): void
    {
        $client = $this->makeClient();
        $payload = $this->validPayload();
        unset($payload['customerId']);
        $this->postOrder($client, $payload);

        // Validation NotBlank → 422 (RFC 7807).
        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetOrderByReferenceReturns200(): void
    {
        $client = $this->makeClient();
        $created = $this->postOrder($client, $this->validPayload());
        $orderId = $created['orderId'];

        $client->request('GET', '/api/orders/'.$orderId, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($orderId, $data['orderId']);
    }

    public function testGetUnknownOrderReturns404(): void
    {
        $client = $this->makeClient();
        $client->request('GET', '/api/orders/ORD-DOESNOTEXIST', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPatchStatusValidTransitionReturns200(): void
    {
        $client = $this->makeClient();
        $created = $this->postOrder($client, $this->validPayload());
        $orderId = $created['orderId'];

        $client->request('PATCH', '/api/orders/'.$orderId.'/status', server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode(['status' => 'PREPARING']));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('PREPARING', $data['status']);
    }

    public function testPatchStatusUnknownValueReturns400(): void
    {
        $client = $this->makeClient();
        $created = $this->postOrder($client, $this->validPayload());
        $orderId = $created['orderId'];

        $client->request('PATCH', '/api/orders/'.$orderId.'/status', server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode(['status' => 'NOT_A_STATUS']));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPatchStatusForbiddenTransitionReturns409(): void
    {
        $client = $this->makeClient();
        $created = $this->postOrder($client, $this->validPayload());
        $orderId = $created['orderId'];

        // PAID → CREATED est interdit (machine à états).
        $client->request('PATCH', '/api/orders/'.$orderId.'/status', server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode(['status' => 'CREATED']));

        $this->assertResponseStatusCodeSame(409);
    }

    public function testCancelOrderReturns200AndCancelled(): void
    {
        $client = $this->makeClient();
        $created = $this->postOrder($client, $this->validPayload());
        $orderId = $created['orderId'];

        $client->request('DELETE', '/api/orders/'.$orderId, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('CANCELLED', $data['status']);
    }

    public function testCannotCancelShippedOrder(): void
    {
        $client = $this->makeClient();
        $created = $this->postOrder($client, $this->validPayload());
        $orderId = $created['orderId'];

        // PAID → PREPARING → SHIPPED, puis tentative d'annulation → 409.
        foreach (['PREPARING', 'SHIPPED'] as $status) {
            $client->request('PATCH', '/api/orders/'.$orderId.'/status', server: [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ], content: json_encode(['status' => $status]));
        }

        $client->request('DELETE', '/api/orders/'.$orderId, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testListCustomerOrders(): void
    {
        $client = $this->makeClient();
        $customer = 'CUST-'.bin2hex(random_bytes(3));
        $this->postOrder($client, $this->validPayload(customerId: $customer));
        $this->postOrder($client, $this->validPayload(customerId: $customer));

        $client->request('GET', '/api/customers/'.$customer.'/orders', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $count = $data['totalItems'] ?? $data['hydra:totalItems'] ?? count($data['member'] ?? $data['hydra:member'] ?? []);
        $this->assertSame(2, $count);
    }

    public function testIdempotencyKeyReturnsSameOrder(): void
    {
        $client = $this->makeClient();
        $key = 'idem-'.bin2hex(random_bytes(6));

        $first = $this->postOrder($client, $this->validPayload(), idempotencyKey: $key);
        $second = $this->postOrder($client, $this->validPayload(), idempotencyKey: $key);

        $this->assertSame($first['orderId'], $second['orderId'], 'Le rejeu avec la même clé doit renvoyer la même commande.');
    }

    public function testPaymentRefusedKeepsOrderFailed(): void
    {
        $client = $this->makeClient(paymentAccept: false);
        $data = $this->postOrder($client, $this->validPayload());

        // Commande créée (201) mais paiement refusé → statut FAILED (Contrat §4).
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('FAILED', $data['status']);
    }
}
