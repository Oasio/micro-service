<?php

namespace App\Service;

use App\Service\Exception\InvalidDependencyException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client du service Catalogue (Django) — appel SYNCHRONE (Contrat §4).
 *
 * Objectif : vérifier que chaque produit existe et récupérer son PRIX OFFICIEL.
 * On ne fait jamais confiance au prix transmis par le client / le panier.
 *
 * GET /api/v1/products/{id}/  →  { "id", "name", "price": "49.95", ... }
 */
class CatalogClient
{
    public function __construct(
        #[Autowire(service: 'catalogue.client')]
        private readonly HttpClientInterface $catalogueClient,
    ) {
    }

    /**
     * Récupère le produit officiel, ou null s'il n'existe pas (404).
     *
     * @throws TransportExceptionInterface si le Catalogue est injoignable (→ géré par le circuit breaker / 502)
     */
    public function getProduct(string $productId): ?ProductInfo
    {
        try {
            $response = $this->catalogueClient->request('GET', '/api/v1/products/'.rawurlencode($productId).'/');
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw $e;
        }

        if (404 === $status) {
            return null;
        }

        if (200 !== $status) {
            // 5xx ou autre : traité comme une panne transport (circuit breaker / 502).
            throw new TransportException('Catalogue : statut HTTP inattendu '.$status);
        }

        try {
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new TransportException('Catalogue : réponse illisible.', 0, $e);
        }

        // Le prix DRF est un décimal (string "49.95"). On refuse un prix absent/non numérique/négatif.
        $rawPrice = $data['price'] ?? null;
        if (!is_numeric($rawPrice) || (float) $rawPrice < 0) {
            throw new InvalidDependencyException(
                sprintf('Prix invalide pour le produit %s dans le Catalogue.', $productId)
            );
        }

        return new ProductInfo(
            productId: (string) ($data['id'] ?? $productId),
            name: (string) ($data['name'] ?? 'Produit '.$productId),
            priceCents: (int) round(((float) $rawPrice) * 100), // centimes (int) pour éviter les arrondis
        );
    }
}
