<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ReviewRepository;
use App\State\ReviewPersistProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une Review = note (1-5) + commentaire + productId + auteur + date.
 *
 * #[ApiResource] (étape 4) génère automatiquement le CRUD complet :
 *   GET /api/reviews, POST /api/reviews, GET /api/reviews/{id}, PATCH, DELETE
 * Doc Swagger gratuite sur /api/docs/, réponses JSON-LD (HATEOAS).
 *
 * Le `processor` branche la vérification du produit auprès du Catalogue (étape 6)
 * avant toute écriture.
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: ReviewPersistProcessor::class),
        new Patch(processor: ReviewPersistProcessor::class),
        new Delete(),
    ],
    paginationItemsPerPage: 20,
)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Note de 1 à 5 (étape 5 — validation).
     */
    #[ORM\Column]
    #[Assert\NotNull(message: 'La note est obligatoire.')]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'Note entre {{ min }} et {{ max }}.',
    )]
    private ?int $rating = null;

    /**
     * Commentaire libre de l'avis.
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le commentaire est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 1000,
        minMessage: 'Le commentaire doit faire au moins {{ limit }} caractères.',
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $comment = null;

    /**
     * Identifiant du produit concerné, dans le service Catalogue (Django).
     * Vérifié via HttpClient avant enregistrement (étape 6).
     */
    #[ORM\Column]
    #[Assert\NotNull(message: 'Le productId est obligatoire.')]
    #[Assert\Positive(message: 'Le productId doit être un entier positif.')]
    private ?int $productId = null;

    /**
     * Auteur de l'avis.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'auteur est obligatoire.")]
    #[Assert\Length(max: 255)]
    private ?string $author = null;

    /**
     * Date de création, positionnée automatiquement côté serveur.
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
