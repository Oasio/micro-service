<?php

namespace App\Service;

use App\Service\Exception\InvalidDependencyException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client du service Panier — appel SYNCHRONE à la création (Contrat §4).
 *
 * Le service Panier n'existant pas encore, un MODE STUB (activé par CART_STUB_ENABLED)
 * renvoie un panier déterministe pour démontrer le flux de bout en bout. Il suffira de
 * passer CART_STUB_ENABLED=false pour appeler le vrai service (GET /carts/{cartId}).
 */
class CartClient
{
    public function __construct(
        #[Autowire(service: 'cart.client')]
        private readonly HttpClientInterface $cartClient,
        #[Autowire('%cart.stub_enabled%')]
        private readonly bool $stubEnabled = true,
    ) {
    }

    /**
     * Récupère les articles d'un panier.
     *
     * @return CartItem[]
     *
     * @throws InvalidDependencyException    si le panier est introuvable ou vide (→ 422)
     * @throws TransportExceptionInterface   si le service Panier est injoignable (→ circuit breaker / 502)
     */
    public function getCart(string $cartId): array
    {
        if ($this->stubEnabled) {
            return $this->stubCart($cartId);
        }

        try {
            $response = $this->cartClient->request('GET', '/carts/'.rawurlencode($cartId));
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw $e;
        }

        if (404 === $status) {
            throw new InvalidDependencyException(sprintf('Panier "%s" introuvable.', $cartId));
        }
        if (200 !== $status) {
            throw new TransportException('Panier : statut HTTP inattendu '.$status);
        }

        try {
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new TransportException('Panier : réponse illisible.', 0, $e);
        }

        $items = [];
        foreach ($data['items'] ?? [] as $line) {
            $items[] = new CartItem(
                productId: (string) ($line['productId'] ?? ''),
                quantity: (int) ($line['quantity'] ?? 0),
            );
        }

        if ([] === $items) {
            throw new InvalidDependencyException(sprintf('Le panier "%s" est vide.', $cartId));
        }

        return $items;
    }

    /**
     * Panier simulé (mode stub). Référence les produits 1 et 2 du Catalogue.
     * Le cartId "CART_UNKNOWN" simule un panier introuvable (pour tester le 422).
     *
     * @return CartItem[]
     */
    private function stubCart(string $cartId): array
    {
        if ('' === $cartId || 'CART_UNKNOWN' === $cartId) {
            throw new InvalidDependencyException(sprintf('Panier "%s" introuvable (stub).', $cartId));
        }

        return [
            new CartItem(productId: '1', quantity: 2),
            new CartItem(productId: '2', quantity: 1),
        ];
    }
}
