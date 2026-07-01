<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Fournisseur d'utilisateur STATELESS pour l'authentification JWT.
 *
 * Le JWT a déjà été vérifié (signature RS256 + expiration) par JwtAuthenticator à l'aide de la
 * clé publique du JWKS de l'Auth. On reconstruit donc simplement l'utilisateur depuis son identité (claim
 * `username`), sans base d'utilisateurs côté Commande — chaque service vérifie, l'Auth gère.
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final class JwtUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new InMemoryUser($identifier, null, ['ROLE_USER']);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // Firewall stateless : rien à recharger entre les requêtes.
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return InMemoryUser::class === $class || is_subclass_of($class, UserInterface::class);
    }
}
