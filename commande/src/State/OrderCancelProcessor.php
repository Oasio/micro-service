<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor de DELETE /orders/{orderId} = ANNULATION (soft cancel) (Contrat §2.4).
 *
 * On ne supprime pas la commande : on la passe en CANCELLED (traçabilité).
 *   - 404 si la commande n'existe pas
 *   - 409 si elle est déjà expédiée/livrée (non annulable)
 *   - 200 + la commande annulée sinon
 *
 * @implements ProcessorInterface<mixed, Order>
 */
final class OrderCancelProcessor implements ProcessorInterface
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

        if (!$order->getStatus()->isCancellable()) {
            throw new HttpException(409, sprintf(
                'Commande non annulable (statut actuel : %s).',
                $order->getStatus()->value,
            ));
        }

        $order->setStatus(OrderStatus::CANCELLED);
        $this->em->flush();

        return $order;
    }
}
