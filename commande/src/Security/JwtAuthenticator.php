<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticator JWT (middleware de vérification — S12.3 étapes 2/3).
 *
 * Sur les routes protégées (firewall `api`), exige un en-tête `Authorization: Bearer <jwt>`,
 * vérifie le JWT RS256 (signature via la clé publique de l'Auth récupérée par JWKS + exp),
 * puis reconstruit l'utilisateur depuis les claims. Tout échec → 401.
 */
final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtVerifier $verifier,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Le firewall limite déjà aux routes protégées ; on traite toutes leurs requêtes.
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($authorization, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('En-tête Authorization Bearer manquant.');
        }

        try {
            $claims = $this->verifier->verify(substr($authorization, 7));
        } catch (\Throwable $e) {
            throw new CustomUserMessageAuthenticationException('JWT invalide : '.$e->getMessage());
        }

        $username = (string) ($claims['username'] ?? $claims['sub'] ?? 'unknown');
        $roles = array_values(array_filter((array) ($claims['roles'] ?? []), 'is_string')) ?: ['ROLE_USER'];

        return new SelfValidatingPassport(
            new UserBadge($username, static fn (): InMemoryUser => new InMemoryUser($username, null, $roles))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // on laisse la requête continuer vers API Platform.
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['code' => 401, 'message' => $exception->getMessageKey() ?: 'Authentification requise.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
