<?php

namespace App\MessageHandler;

use App\Message\OrderConfirmed;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consommateur de l'événement OrderConfirmed (Contrat §5.2).
 *
 * Dans une vraie architecture, ce sont les services Stock / Notifications / Logistique
 * qui s'abonnent (chacun son propre consommateur). Ici, pour la démonstration, on trace
 * la diffusion. Avec MESSENGER_TRANSPORT_DSN=sync:// ce handler tourne en process ;
 * avec amqp:// il est exécuté par un worker (messenger:consume async).
 */
#[AsMessageHandler]
final class OrderConfirmedHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderConfirmed $event): void
    {
        $this->logger->info('Événement OrderConfirmed diffusé (Stock / Notifications / Logistique s\'abonneraient ici).', [
            'orderId' => $event->orderId,
            'customerId' => $event->customerId,
            'totalAmountCents' => $event->totalAmountCents,
            'currency' => $event->currency,
            'items' => $event->items,
        ]);
    }
}
