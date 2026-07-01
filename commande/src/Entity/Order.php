<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Dto\CreateOrderRequest;
use App\Dto\UpdateOrderStatusRequest;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\State\CustomerOrdersProvider;
use App\State\OrderCancelProcessor;
use App\State\OrderCreateProcessor;
use App\State\OrderStatusProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Commande FlexShop — ressource centrale du service (cf. Contrat d'API).
 *
 * Identifiée publiquement par sa `reference` (orderId, ex. "ORD-1A2B3C4D") ; l'`id`
 * entier reste interne. Les montants sont stockés en CENTIMES (int) pour éviter les
 * arrondis des float (Contrat §5.4), et exposés en euros à la sérialisation.
 *
 * #[ApiResource] mappe les 6 opérations du contrat (Swagger sur /api/docs) :
 *   POST   /orders                         créer (orchestration Panier→Catalogue→Paiement)
 *   GET    /orders                         lister
 *   GET    /orders/{orderId}               détail
 *   GET    /customers/{customerId}/orders  commandes d'un client
 *   PATCH  /orders/{orderId}/status        changer le statut (machine à états)
 *   DELETE /orders/{orderId}               annuler (soft cancel)
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Order',
    operations: [
        new GetCollection(
            uriTemplate: '/orders',
        ),
        new Get(
            uriTemplate: '/orders/{orderId}',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['reference'])],
        ),
        new GetCollection(
            uriTemplate: '/customers/{customerId}/orders',
            uriVariables: ['customerId' => new Link(fromClass: Order::class, identifiers: ['customerId'])],
            provider: CustomerOrdersProvider::class,
        ),
        new Post(
            uriTemplate: '/orders',
            input: CreateOrderRequest::class,
            processor: OrderCreateProcessor::class,
            status: 201,
            // Documente les codes d'erreur métier dans la spec OpenAPI publiée (Contrat §2.1).
            openapi: new OpenApiOperation(responses: [
                '422' => new OpenApiResponse(description: 'Validation échouée ou dépendance invalide (panier introuvable/vide, produit inexistant, quantité/montant invalide).'),
                '409' => new OpenApiResponse(description: 'Une création est déjà en cours pour cette Idempotency-Key.'),
                '502' => new OpenApiResponse(description: 'Une dépendance (Panier, Catalogue, Paiement) est injoignable.'),
            ]),
        ),
        new Patch(
            uriTemplate: '/orders/{orderId}/status',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['reference'])],
            input: UpdateOrderStatusRequest::class,
            read: false,
            processor: OrderStatusProcessor::class,
            openapi: new OpenApiOperation(responses: [
                '400' => new OpenApiResponse(description: 'Statut inexistant (hors énumération).'),
                '404' => new OpenApiResponse(description: 'Commande introuvable.'),
                '409' => new OpenApiResponse(description: 'Transition de statut interdite (machine à états).'),
            ]),
        ),
        new Delete(
            uriTemplate: '/orders/{orderId}',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['reference'])],
            read: false,
            output: Order::class,
            status: 200,
            processor: OrderCancelProcessor::class,
            openapi: new OpenApiOperation(responses: [
                '404' => new OpenApiResponse(description: 'Commande introuvable.'),
                '409' => new OpenApiResponse(description: 'Commande déjà expédiée/livrée : non annulable.'),
            ]),
        ),
    ],
    normalizationContext: ['groups' => ['order:read']],
    paginationItemsPerPage: 20,
)]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    /** Référence publique de la commande (orderId du contrat). */
    #[ORM\Column(length: 32, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['order:read'])]
    #[SerializedName('orderId')]
    private string $reference;

    #[ORM\Column(length: 64)]
    #[Groups(['order:read'])]
    private string $customerId;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $cartId = null;

    /** Email du client, transmis au service Paiement (non exposé dans la réponse). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $customerEmail = null;

    #[ORM\Column(enumType: OrderStatus::class)]
    #[Groups(['order:read'])]
    private OrderStatus $status = OrderStatus::CREATED;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['order:read'])]
    private Collection $items;

    /** Montant total officiel, en centimes. */
    #[ORM\Column]
    private int $totalAmountCents = 0;

    #[ORM\Column(length: 3)]
    #[Groups(['order:read'])]
    private string $currency = 'EUR';

    #[ORM\Embedded(class: ShippingAddress::class)]
    #[Groups(['order:read'])]
    private ShippingAddress $shippingAddress;

    /** Clé d'idempotence (Idempotency-Key) — évite la double création/débit (Contrat §5.1). */
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $idempotencyKey = null;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $customerId)
    {
        $this->reference = 'ORD-'.strtoupper(bin2hex(random_bytes(4)));
        $this->customerId = $customerId;
        $this->items = new ArrayCollection();
        $this->shippingAddress = new ShippingAddress();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getCartId(): ?string
    {
        return $this->cartId;
    }

    public function setCartId(?string $cartId): static
    {
        $this->cartId = $cartId;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function getTotalAmountCents(): int
    {
        return $this->totalAmountCents;
    }

    public function setTotalAmountCents(int $totalAmountCents): static
    {
        $this->totalAmountCents = $totalAmountCents;

        return $this;
    }

    /** Recalcule le total à partir des lignes (somme des sous-totaux). */
    public function recalculateTotal(): static
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getLineTotalCents();
        }
        $this->totalAmountCents = $total;

        return $this;
    }

    /** Montant total en euros, exposé dans l'API (ex. 149.90). */
    #[Groups(['order:read'])]
    #[SerializedName('totalAmount')]
    public function getTotalAmount(): float
    {
        return $this->totalAmountCents / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getShippingAddress(): ShippingAddress
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(ShippingAddress $shippingAddress): static
    {
        $this->shippingAddress = $shippingAddress;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
