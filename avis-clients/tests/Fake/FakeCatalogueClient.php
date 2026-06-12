<?php

namespace App\Tests\Fake;

use App\Service\CatalogueClient;

/**
 * Double de test du CatalogueClient : on contrôle quels productId sont réputés exister,
 * sans dépendre du vrai service Django pendant les tests.
 */
class FakeCatalogueClient extends CatalogueClient
{
    /**
     * @param int[] $existingProductIds liste des ids considérés comme existants
     */
    public function __construct(private array $existingProductIds = [1])
    {
        // On ne sollicite pas le HttpClient parent : pas d'appel à parent::__construct().
    }

    public function productExists(int $productId): bool
    {
        return in_array($productId, $this->existingProductIds, true);
    }
}
