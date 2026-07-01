<?php

namespace App\Service\Exception;

/**
 * Une ressource liée est invalide/inexistante : panier introuvable, produit absent
 * du Catalogue, panier vide…
 *
 * Mappée en HTTP 422 (Unprocessable Entity) par les State processors (cf. Contrat §2.1).
 */
class InvalidDependencyException extends \RuntimeException
{
}
