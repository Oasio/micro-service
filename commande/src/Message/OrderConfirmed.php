<?php

namespace App\Message;

/**
 * Événement publié quand une commande est payée (Contrat §5.2 — communication ASYNCHRONE).
 *
 * Plutôt que d'enchaîner N appels REST (Stock, Notifications, Logistique…), Commande
 * PUBLIE cet événement sur le broker ; les consommateurs s'y abonnent → découplage.
 * Commande ne sait pas qui écoute.
 *
 * @param list<array{productId: string, quantity: int}> $items
 */
final readonly class OrderConfirmed
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public int $totalAmountCents,
        public string $currency,
        public array $items,
    ) {
    }
}
