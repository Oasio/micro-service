<?php

namespace App\Service;

use App\Service\Exception\DependencyUnavailableException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Disjoncteur (circuit breaker) — pattern de fiabilité S6.
 *
 * Après N échecs d'une dépendance, on « ouvre le circuit » : pendant une période de repos
 * on cesse d'appeler le service en panne et on échoue vite (fail-fast), au lieu d'accumuler
 * des timeouts. À l'expiration du délai, les appels reprennent : un succès referme le circuit
 * (compteur remis à zéro), un nouvel échec le rouvre. (Half-open simplifié : on n'isole pas
 * un unique appel d'essai — suffisant pour le modèle requête/process synchrone de PHP.)
 *
 * L'état est persité dans le cache applicatif (partagé entre requêtes).
 */
class CircuitBreaker
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 30,
    ) {
    }

    /**
     * Exécute $operation sous protection du disjoncteur pour la dépendance $service.
     *
     * @template T
     *
     * @param callable():T $operation
     *
     * @return T
     *
     * @throws DependencyUnavailableException si le circuit est ouvert ou si l'appel échoue (transport)
     */
    public function call(string $service, callable $operation): mixed
    {
        if ($this->isOpen($service)) {
            throw new DependencyUnavailableException(
                sprintf('Circuit ouvert pour "%s" : service considéré en panne, appel court-circuité.', $service)
            );
        }

        try {
            $result = $operation();
            $this->recordSuccess($service);

            return $result;
        } catch (TransportExceptionInterface $e) {
            // Échec transport (timeout, connexion refusée, retries épuisés) → on compte l'échec.
            $this->recordFailure($service);

            throw new DependencyUnavailableException(
                sprintf('Le service "%s" est injoignable.', $service),
                0,
                $e
            );
        }
        // NB : une erreur métier (ex. produit 404 → InvalidDependencyException) n'ouvre PAS
        // le circuit : elle traverse cette méthode sans être interceptée.
    }

    public function isOpen(string $service): bool
    {
        $item = $this->cache->getItem($this->key($service, 'open_until'));
        if (!$item->isHit()) {
            return false;
        }

        return (int) $item->get() > time();
    }

    public function recordSuccess(string $service): void
    {
        $this->cache->deleteItems([
            $this->key($service, 'failures'),
            $this->key($service, 'open_until'),
        ]);
    }

    public function recordFailure(string $service): void
    {
        $failuresItem = $this->cache->getItem($this->key($service, 'failures'));
        $failures = ($failuresItem->isHit() ? (int) $failuresItem->get() : 0) + 1;

        $failuresItem->set($failures)->expiresAfter($this->cooldownSeconds * 2);
        $this->cache->save($failuresItem);

        if ($failures >= $this->failureThreshold) {
            $openItem = $this->cache->getItem($this->key($service, 'open_until'));
            $openItem->set(time() + $this->cooldownSeconds)->expiresAfter($this->cooldownSeconds);
            $this->cache->save($openItem);
        }
    }

    private function key(string $service, string $suffix): string
    {
        return sprintf('cb.%s.%s', preg_replace('/[^a-zA-Z0-9_.]/', '_', $service), $suffix);
    }
}
