<?php

namespace App\Service;

/**
 * Produit officiel renvoyé par le service Catalogue (Django).
 * Le prix est conservé en CENTIMES (int) pour éviter les arrondis float.
 */
final readonly class ProductInfo
{
    public function __construct(
        public string $productId,
        public string $name,
        public int $priceCents,
    ) {
    }
}
