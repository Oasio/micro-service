<?php

namespace App\Service\Exception;

/**
 * Une dépendance (Panier, Catalogue, Paiement) est injoignable :
 * timeout/retries épuisés, ou circuit breaker ouvert.
 *
 * Mappée en HTTP 502 (Bad Gateway) par les State processors (cf. Contrat §2.1).
 */
class DependencyUnavailableException extends \RuntimeException
{
}
