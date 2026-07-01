<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Order;
use App\Repository\OrderRepository;

/**
 * Provider de GET /customers/{customerId}/orders (Contrat §2.3) :
 * les commandes d'un client, de la plus récente à la plus ancienne.
 *
 * @implements ProviderInterface<Order>
 */
final class CustomerOrdersProvider implements ProviderInterface
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        return $this->orders->findByCustomerId((string) ($uriVariables['customerId'] ?? ''));
    }
}
