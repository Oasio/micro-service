<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\UpdateOrderStatusRequest;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor de PATCH /orders/{orderId}/status (Contrat §2.5).
 *
 * Codes exacts du contrat :
 *   - 404 si la commande n'existe pas
 *   - 400 si le statut est hors énumération
 *   - 409 si la transition est interdite (machine à états, ex. DELIVERED → CREATED)
 *   - 200 sinon
 *
 * @implements ProcessorInterface<UpdateOrderStatusRequest, Order>
 */
final class OrderStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        $order = $this->orders->findOneByReference((string) ($uriVariables['orderId'] ?? ''));
        if (null === $order) {
            throw new NotFoundHttpException('Commande introuvable.');
        }

        $requested = $data->status;
        $target = null !== $requested ? OrderStatus::tryFrom($requested) : null;
        if (null === $target) {
            // Statut absent ou hors énumération → 400.
            throw new HttpException(400, sprintf('Statut invalide : "%s".', (string) $requested));
        }

        if (!$order->getStatus()->canTransitionTo($target)) {
            // Transition interdite → 409.
            throw new HttpException(409, sprintf(
                'Transition interdite : %s → %s.',
                $order->getStatus()->value,
                $target->value,
            ));
        }

        $order->setStatus($target);
        $this->em->flush();

        return $order;
    }
}
