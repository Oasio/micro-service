<?php

namespace App\Tests\Fake;

use App\Service\PaymentClient;
use App\Service\PaymentResult;

/**
 * Double de test du PaymentClient : accepte ou refuse de façon déterministe,
 * en reproduisant les statuts du vrai payment-service (succeeded / failed).
 */
class FakePaymentClient extends PaymentClient
{
    public function __construct(private bool $accept = true)
    {
        // Pas d'appel à parent::__construct().
    }

    public function charge(string $orderReference, int $amountCents, string $customerEmail, string $currency = 'EUR'): PaymentResult
    {
        if (!$this->accept) {
            return new PaymentResult(accepted: false, status: 'failed', message: 'Paiement refusé (fake).');
        }

        return new PaymentResult(accepted: true, status: 'succeeded', transactionId: 'pay_testfake01');
    }
}
