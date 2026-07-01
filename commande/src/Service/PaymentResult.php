<?php

namespace App\Service;

/**
 * Résultat d'un appel synchrone au service Paiement (payment-service).
 *
 * Le payment-service renvoie un `status` parmi : requires_payment_method,
 * requires_action, processing, succeeded, failed, canceled.
 *  - accepted = (status === 'succeeded')
 *  - pending  = status en cours (processing / requires_*)
 *  - sinon    = refusé (failed / canceled)
 */
final readonly class PaymentResult
{
    public function __construct(
        public bool $accepted,
        public bool $pending = false,
        public ?string $status = null,
        public ?string $transactionId = null,
        public ?string $message = null,
    ) {
    }
}
