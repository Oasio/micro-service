<?php

namespace App\Service;

use App\Dto\CreateOrderRequest;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ShippingAddress;
use App\Enum\OrderStatus;
use App\Message\OrderConfirmed;
use App\Service\Exception\InvalidDependencyException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestrateur de la création de commande (Contrat §4).
 *
 * Enchaînement à la création de POST /orders :
 *   1. GET Panier      → récupérer les articles (sync, circuit breaker)
 *   2. GET Catalogue   → vérifier chaque produit + PRIX OFFICIEL (sync, circuit breaker)
 *   3. calcul du totalAmount par Commande (jamais le prix client)
 *   4. persister la commande en PENDING_PAYMENT (elle survit même si le paiement échoue)
 *   5. POST Paiement   → débiter (sync, circuit breaker, idempotent)
 *   6. PAID + publication de l'événement async OrderConfirmed, sinon FAILED
 *
 * Exceptions métier remontées (mappées en HTTP par le State processor) :
 *   - InvalidDependencyException     → 422 (panier/produit invalide)
 *   - DependencyUnavailableException → 502 (dépendance injoignable)
 */
class OrderOrchestrator
{
    /** Garde-fou : quantité par ligne plausible (évite paniers aberrants / débordement). */
    private const MAX_QUANTITY_PER_ITEM = 1000;

    public function __construct(
        private readonly CartClient $cartClient,
        private readonly CatalogClient $catalogClient,
        private readonly PaymentClient $paymentClient,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function createOrder(CreateOrderRequest $request, ?string $idempotencyKey = null): Order
    {
        // 1. Panier (sync, protégé par le circuit breaker).
        $cartItems = $this->circuitBreaker->call(
            'cart',
            fn (): array => $this->cartClient->getCart((string) $request->cartId),
        );

        $order = new Order((string) $request->customerId);
        $order->setCartId($request->cartId);
        $order->setShippingAddress($this->mapAddress($request));
        $order->setIdempotencyKey($idempotencyKey);
        // Le service Paiement exige un email ; à défaut on en dérive un depuis le customerId.
        $order->setCustomerEmail($request->customerEmail ?: $request->customerId.'@flexshop.local');

        // 2-3. Catalogue : prix officiel par produit + calcul du total.
        foreach ($cartItems as $cartItem) {
            if ($cartItem->quantity < 1 || $cartItem->quantity > self::MAX_QUANTITY_PER_ITEM) {
                throw new InvalidDependencyException(sprintf(
                    'Quantité invalide pour le produit %s : %d (attendu entre 1 et %d).',
                    $cartItem->productId,
                    $cartItem->quantity,
                    self::MAX_QUANTITY_PER_ITEM,
                ));
            }

            $product = $this->circuitBreaker->call(
                'catalogue',
                fn (): ?ProductInfo => $this->catalogClient->getProduct($cartItem->productId),
            );

            if (null === $product) {
                throw new InvalidDependencyException(
                    sprintf('Produit inexistant dans le Catalogue : %s.', $cartItem->productId)
                );
            }

            $order->addItem(new OrderItem(
                productId: $product->productId,
                productName: $product->name,
                quantity: $cartItem->quantity,
                unitPriceCents: $product->priceCents,
            ));
        }

        $order->recalculateTotal();

        // Une commande à montant nul (ou négatif) ne doit pas partir en paiement.
        if ($order->getTotalAmountCents() <= 0) {
            throw new InvalidDependencyException('Montant de commande invalide (total nul).');
        }

        // 4. Persiste la commande AVANT le paiement : elle reste en base même si le
        //    Paiement est injoignable (PENDING_PAYMENT, réessai possible — Contrat §4).
        $order->setStatus(OrderStatus::PENDING_PAYMENT);
        $this->em->persist($order);
        $this->em->flush();

        // 5. Paiement synchrone (idempotent via Transaction-Token). Si injoignable → 502, commande conservée.
        $payment = $this->circuitBreaker->call(
            'payment',
            fn (): PaymentResult => $this->paymentClient->charge(
                $order->getReference(),
                $order->getTotalAmountCents(),
                (string) $order->getCustomerEmail(),
                $order->getCurrency(),
            ),
        );

        // 6. Issue du paiement.
        if ($payment->accepted) {
            $order->setStatus(OrderStatus::PAID);
            $this->em->flush();
            $this->publishOrderConfirmed($order);
        } elseif ($payment->pending) {
            // Paiement en cours (processing / requires_*) : la commande reste en attente.
            $this->em->flush();
        } else {
            $order->setStatus(OrderStatus::FAILED);
            $this->em->flush();
        }

        return $order;
    }

    private function mapAddress(CreateOrderRequest $request): ShippingAddress
    {
        $a = $request->shippingAddress;

        return new ShippingAddress(
            street: (string) ($a?->street ?? ''),
            city: (string) ($a?->city ?? ''),
            postalCode: (string) ($a?->postalCode ?? ''),
            country: (string) ($a?->country ?? ''),
        );
    }

    /**
     * Publie l'événement async OrderConfirmed (découplage Stock/Notifications/Logistique).
     */
    private function publishOrderConfirmed(Order $order): void
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = ['productId' => $item->getProductId(), 'quantity' => $item->getQuantity()];
        }

        $this->messageBus->dispatch(new OrderConfirmed(
            orderId: $order->getReference(),
            customerId: $order->getCustomerId(),
            totalAmountCents: $order->getTotalAmountCents(),
            currency: $order->getCurrency(),
            items: $items,
        ));
    }
}
