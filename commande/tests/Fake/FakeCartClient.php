<?php

namespace App\Tests\Fake;

use App\Service\CartClient;
use App\Service\CartItem;
use App\Service\Exception\InvalidDependencyException;

/**
 * Double de test du CartClient : panier déterministe, sans appeler de vrai service.
 */
class FakeCartClient extends CartClient
{
    /**
     * @param CartItem[] $items
     */
    public function __construct(private array $items = [])
    {
        // Pas d'appel à parent::__construct() : on ne sollicite pas le HttpClient.
        if ([] === $this->items) {
            $this->items = [
                new CartItem(productId: '1', quantity: 2),
                new CartItem(productId: '2', quantity: 1),
            ];
        }
    }

    public function getCart(string $cartId): array
    {
        if ('' === $cartId || 'CART_UNKNOWN' === $cartId) {
            throw new InvalidDependencyException(sprintf('Panier "%s" introuvable (fake).', $cartId));
        }

        return $this->items;
    }
}
