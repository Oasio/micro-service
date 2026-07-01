<?php

namespace App\Enum;

/**
 * Cycle de vie d'une commande (cf. Contrat d'API — §3 énumération des statuts).
 *
 *   CREATED → PENDING_PAYMENT → PAID → PREPARING → SHIPPED → DELIVERED
 *      └──────────────┴── CANCELLED (tant que non SHIPPED)
 *
 * FAILED : le paiement a été refusé définitivement.
 */
enum OrderStatus: string
{
    case CREATED = 'CREATED';                 // commande initialisée (panier validé, montant calculé)
    case PENDING_PAYMENT = 'PENDING_PAYMENT'; // en attente du résultat du service Paiement
    case PAID = 'PAID';                       // paiement confirmé
    case PREPARING = 'PREPARING';             // en préparation
    case SHIPPED = 'SHIPPED';                 // expédiée
    case DELIVERED = 'DELIVERED';             // livrée
    case CANCELLED = 'CANCELLED';             // annulée (possible tant que non SHIPPED)
    case FAILED = 'FAILED';                   // paiement refusé

    /**
     * Transitions autorisées depuis chaque statut.
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::CREATED => [self::PENDING_PAYMENT, self::CANCELLED],
            self::PENDING_PAYMENT => [self::PAID, self::FAILED, self::CANCELLED],
            self::PAID => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED, self::CANCELLED, self::FAILED => [],
        };
    }

    /**
     * La transition vers $target est-elle valide depuis le statut courant ?
     */
    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Une commande peut-elle encore être annulée depuis ce statut ?
     * (Tant qu'elle n'est ni expédiée, ni livrée, ni déjà annulée/échouée.)
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::CREATED, self::PENDING_PAYMENT, self::PAID, self::PREPARING], true);
    }
}
