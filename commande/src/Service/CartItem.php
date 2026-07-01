<?php

namespace App\Service;

/**
 * Article tel que renvoyé par le service Panier (productId + quantité).
 * Le prix N'est PAS pris du panier : il est récupéré au prix officiel du Catalogue.
 */
final readonly class CartItem
{
    public function __construct(
        public string $productId,
        public int $quantity,
    ) {
    }
}
