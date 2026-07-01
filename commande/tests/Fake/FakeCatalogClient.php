<?php

namespace App\Tests\Fake;

use App\Service\CatalogClient;
use App\Service\ProductInfo;

/**
 * Double de test du CatalogClient : catalogue déterministe (prix en centimes),
 * sans dépendre du vrai service Django.
 */
class FakeCatalogClient extends CatalogClient
{
    /**
     * @param array<string, array{0: string, 1: int}> $catalog productId => [nom, prixCentimes]
     */
    public function __construct(private array $catalog = [])
    {
        // Pas d'appel à parent::__construct().
        if ([] === $this->catalog) {
            $this->catalog = [
                '1' => ['Casque Bluetooth', 4995], // 49,95 €
                '2' => ['Souris sans fil', 1999],  // 19,99 €
            ];
        }
    }

    public function getProduct(string $productId): ?ProductInfo
    {
        if (!isset($this->catalog[$productId])) {
            return null; // produit inexistant
        }

        [$name, $cents] = $this->catalog[$productId];

        return new ProductInfo(productId: $productId, name: $name, priceCents: $cents);
    }
}
