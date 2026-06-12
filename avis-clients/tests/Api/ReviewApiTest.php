<?php

namespace App\Tests\Api;

use App\Service\CatalogueClient;
use App\Tests\Fake\FakeCatalogueClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests d'API du service Avis-Clients (étape 8 — WebTestCase, équivalent APITestCase Django).
 *
 * Le CatalogueClient est remplacé par un double : les produits 1 et 2 sont réputés
 * exister, le reste non. On teste ainsi la logique sans dépendre du Django réel.
 *
 * Pré-requis : une base de test migrée (voir README, section "Tests").
 */
class ReviewApiTest extends WebTestCase
{
    private function makeClient(array $existingProductIds = [1, 2]): KernelBrowser
    {
        $client = static::createClient();

        // Remplace le client Catalogue par un double déterministe.
        static::getContainer()->set(CatalogueClient::class, new FakeCatalogueClient($existingProductIds));

        return $client;
    }

    private function postReview(KernelBrowser $client, array $payload): void
    {
        $client->request('POST', '/api/reviews', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode($payload));
    }

    public function testListReviewsRespondsOk(): void
    {
        $client = $this->makeClient();

        $client->request('GET', '/api/reviews', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testCreateReviewReturns201(): void
    {
        $client = $this->makeClient();

        $this->postReview($client, [
            'rating' => 5,
            'comment' => 'Produit au top, je recommande !',
            'productId' => 1,
            'author' => 'Alice',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(5, $data['rating']);
        $this->assertSame('Alice', $data['author']);
    }

    public function testCreateReviewWithUnknownProductReturns422(): void
    {
        $client = $this->makeClient(existingProductIds: [1, 2]);

        // productId 9999 n'existe pas dans le Catalogue (double) => refus 422.
        $this->postReview($client, [
            'rating' => 4,
            'comment' => 'Avis sur un produit fantôme.',
            'productId' => 9999,
            'author' => 'Bob',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateReviewWithRatingAboveFiveReturns422(): void
    {
        $client = $this->makeClient();

        // rating = 6 hors bornes => 422 via la validation (slide 40 / checkpoint 2).
        $this->postReview($client, [
            'rating' => 6,
            'comment' => 'Note impossible.',
            'productId' => 1,
            'author' => 'Charlie',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateReviewWithBlankCommentReturns422(): void
    {
        $client = $this->makeClient();

        $this->postReview($client, [
            'rating' => 3,
            'comment' => '',
            'productId' => 1,
            'author' => 'Dora',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
