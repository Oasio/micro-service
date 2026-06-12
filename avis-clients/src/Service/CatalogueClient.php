<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Petit client encapsulant les appels au service Catalogue (Django) — étape 6.
 *
 * Le HttpClient scopé `catalogue.client` (config/packages/http_client.yaml) est
 * injecté ici via l'autowiring par nom : $catalogueClient.
 */
class CatalogueClient
{
    public function __construct(
        #[Autowire(service: 'catalogue.client')]
        private readonly HttpClientInterface $catalogueClient,
    ) {
    }

    /**
     * Vérifie que le produit existe dans le Catalogue.
     *
     * Appelle GET /api/v1/products/{id}/ et renvoie true si le statut est 200.
     *
     * @throws TransportExceptionInterface si le service Catalogue est injoignable
     *                                     (ConnectException — piège classique slide 44)
     */
    public function productExists(int $productId): bool
    {
        try {
            $response = $this->catalogueClient->request(
                'GET',
                '/api/v1/products/'.$productId.'/',
            );

            return 200 === $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            // Service Catalogue injoignable : on laisse remonter pour un 502/503 explicite.
            throw $e;
        } catch (ExceptionInterface) {
            // Toute autre erreur HTTP (404, 5xx...) => produit non confirmé.
            return false;
        }
    }
}
