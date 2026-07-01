<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CreateOrderRequest;
use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\Exception\DependencyUnavailableException;
use App\Service\Exception\InvalidDependencyException;
use App\Service\OrderOrchestrator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Processor de POST /orders : déclenche l'orchestration (Panier→Catalogue→Paiement)
 * et mappe les erreurs métier sur les codes HTTP du contrat (422 / 502).
 *
 * Idempotence (Contrat §5.1) : si l'en-tête Idempotency-Key a déjà servi, on renvoie
 * la commande existante sans rejouer le flux (pas de double création / double débit).
 *
 * @implements ProcessorInterface<CreateOrderRequest, Order>
 */
final class OrderCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrderOrchestrator $orchestrator,
        private readonly OrderRepository $orders,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        $idempotencyKey = $this->requestStack->getCurrentRequest()?->headers->get('Idempotency-Key');

        // Rejeu idempotent : même clé → même commande, sans réexécuter.
        if (null !== $idempotencyKey && '' !== $idempotencyKey) {
            $existing = $this->orders->findOneByIdempotencyKey($idempotencyKey);
            if (null !== $existing) {
                return $existing;
            }
        }

        try {
            return $this->orchestrator->createOrder($data, $idempotencyKey ?: null);
        } catch (InvalidDependencyException $e) {
            // Panier introuvable / vide, produit inexistant, quantité/montant invalide → 422 (Contrat §2.1).
            throw new HttpException(422, $e->getMessage(), $e);
        } catch (DependencyUnavailableException $e) {
            // Dépendance injoignable (timeout/retries épuisés, circuit ouvert) → 502.
            throw new HttpException(502, $e->getMessage(), $e);
        } catch (UniqueConstraintViolationException $e) {
            // Course entre deux requêtes concurrentes portant la même Idempotency-Key :
            // au lieu d'un 500, on signale clairement le conflit (création déjà en cours).
            throw new ConflictHttpException(
                'Une commande est déjà en cours de création pour cette Idempotency-Key.',
                $e,
            );
        }
    }
}
