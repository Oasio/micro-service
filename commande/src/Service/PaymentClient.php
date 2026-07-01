<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client du service Paiement (payment-service de l'équipe Paiement) — appel SYNCHRONE.
 *
 * Contrat réel (payment-service/openapi.yaml) :
 *   POST /api/v1/payments
 *   Header  : Transaction-Token: <UUIDv4>   (anti-doublon, idempotence — Contrat §5.1)
 *   Body    : { amount, currency, orderId, customerEmail, metadata? }
 *   Réponses: 201 créé / 200 rejeu (même token) / 400 invalide / 409 token en cours
 *   Body    : { id (pay_...), orderId, amount, currency, status, clientSecret, createdAt }
 *   status  : requires_payment_method | requires_action | processing | succeeded | failed | canceled
 *
 * Le payment-service n'étant pas forcément lancé, un MODE STUB (PAYMENT_STUB_ENABLED)
 * simule la réponse : succeeded par défaut, failed au-delà de 10 000 € (démo du refus).
 */
class PaymentClient
{
    private const DECLINE_THRESHOLD_CENTS = 1_000_000; // 10 000 € → refus simulé.

    public function __construct(
        #[Autowire(service: 'payment.client')]
        private readonly HttpClientInterface $paymentClient,
        #[Autowire('%payment.stub_enabled%')]
        private readonly bool $stubEnabled = true,
    ) {
    }

    /**
     * Débite le client pour la commande.
     *
     * Le Transaction-Token est dérivé de façon DÉTERMINISTE de la référence de commande :
     * un retry pour la même commande réutilise le même token → pas de double débit.
     *
     * @throws TransportExceptionInterface si le service Paiement est injoignable (→ circuit breaker / 502)
     */
    public function charge(string $orderReference, int $amountCents, string $customerEmail, string $currency = 'EUR'): PaymentResult
    {
        if ($this->stubEnabled) {
            return $this->stubCharge($amountCents);
        }

        try {
            $response = $this->paymentClient->request('POST', '/api/v1/payments', [
                'headers' => [
                    'Transaction-Token' => $this->transactionToken($orderReference),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => round($amountCents / 100, 2),
                    'currency' => $currency,
                    'orderId' => $orderReference,
                    'customerEmail' => $customerEmail,
                    'metadata' => ['source' => 'commande-service'],
                ],
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw $e;
        }

        // 201 (créé) et 200 (même token déjà consommé → même réponse) sont les cas nominaux.
        // 409 (token en cours) est transitoire : on le traite comme une panne transport (retry/circuit breaker).
        if (200 !== $status && 201 !== $status) {
            throw new TransportException('Paiement : statut HTTP inattendu '.$status);
        }

        try {
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new TransportException('Paiement : réponse illisible.', 0, $e);
        }

        return $this->mapResponse($data);
    }

    private function mapResponse(array $data): PaymentResult
    {
        $status = (string) ($data['status'] ?? 'failed');
        $transactionId = $data['id'] ?? null;

        $accepted = 'succeeded' === $status;
        $pending = in_array($status, ['processing', 'requires_payment_method', 'requires_action'], true);

        return new PaymentResult(
            accepted: $accepted,
            pending: $pending,
            status: $status,
            transactionId: $transactionId,
        );
    }

    private function stubCharge(int $amountCents): PaymentResult
    {
        if ($amountCents >= self::DECLINE_THRESHOLD_CENTS) {
            return new PaymentResult(accepted: false, status: 'failed', message: 'Montant trop élevé (refus simulé).');
        }

        return new PaymentResult(
            accepted: true,
            status: 'succeeded',
            transactionId: 'pay_'.bin2hex(random_bytes(5)),
        );
    }

    /**
     * Dérive un Transaction-Token (UUID v4 valide, déterministe) depuis la référence de commande.
     */
    private function transactionToken(string $orderReference): string
    {
        $h = md5('commande:'.$orderReference);

        return sprintf(
            '%s-%s-4%s-%x%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 13, 3),
            (hexdec($h[16]) & 0x3) | 0x8,
            substr($h, 17, 3),
            substr($h, 20, 12),
        );
    }
}
