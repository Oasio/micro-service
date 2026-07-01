<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check (Contrat §2 / S6) : sonde de disponibilité du service et de sa base.
 *
 * GET /health → 200 si tout va bien, 503 si la base est injoignable.
 */
class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%cart.stub_enabled%')] private readonly bool $cartStub,
        #[Autowire('%payment.stub_enabled%')] private readonly bool $paymentStub,
        #[Autowire('%catalogue.base_url%')] private readonly string $catalogueUrl,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $database = 'ok';
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $database = 'ko';
        }

        return new JsonResponse([
            'status' => 'ok' === $database ? 'ok' : 'degraded',
            'service' => 'commande',
            'database' => $database,
            'dependencies' => [
                'catalogue' => ['url' => $this->catalogueUrl, 'mode' => 'live'],
                'cart' => ['mode' => $this->cartStub ? 'stub' : 'live'],
                'payment' => ['mode' => $this->paymentStub ? 'stub' : 'live'],
            ],
        ], 'ok' === $database ? 200 : 503);
    }
}
