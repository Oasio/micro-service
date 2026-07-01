<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Ligne de commande : un produit (vérifié dans le Catalogue), sa quantité et son
 * prix unitaire OFFICIEL (jamais le prix envoyé par le client — cf. Contrat §4).
 *
 * Les montants sont stockés en CENTIMES (int) pour éviter les arrondis des float
 * (recommandation fintech du Contrat §5.4) et exposés en euros à la sérialisation.
 */
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(length: 64)]
    #[Groups(['order:read'])]
    private string $productId;

    #[ORM\Column(length: 255)]
    #[Groups(['order:read'])]
    private string $productName;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private int $quantity;

    /** Prix unitaire officiel, en centimes. */
    #[ORM\Column]
    private int $unitPriceCents;

    public function __construct(string $productId, string $productName, int $quantity, int $unitPriceCents)
    {
        $this->productId = $productId;
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->unitPriceCents = $unitPriceCents;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    /** Prix unitaire en euros, exposé dans l'API (ex. 49.95). */
    #[Groups(['order:read'])]
    #[SerializedName('unitPrice')]
    public function getUnitPrice(): float
    {
        return $this->unitPriceCents / 100;
    }

    /** Sous-total de la ligne, en centimes. */
    public function getLineTotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }
}
