<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Review;
use App\Service\CatalogueClient;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * State processor décorant le persist processor Doctrine d'API Platform (étape 6).
 *
 * Avant d'enregistrer un avis (POST / PATCH), on vérifie via HttpClient que le
 * productId existe bien dans le service Catalogue. Si le produit n'existe pas,
 * on refuse l'avis avec un code 422 (Unprocessable Entity) — comme demandé aux
 * checkpoints 2 et 3.
 *
 * @implements ProcessorInterface<Review, Review|void>
 */
final class ReviewPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Review, Review> $persistProcessor processor Doctrine (écriture en base)
     * @param ProcessorInterface<Review, void>   $removeProcessor  processor Doctrine (suppression)
     */
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly ProcessorInterface $removeProcessor,
        private readonly CatalogueClient $catalogueClient,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Review) {
            $this->assertProductExists($data->getProductId());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * @throws HttpException 422 si le produit n'existe pas, 502 si le Catalogue est injoignable
     */
    private function assertProductExists(?int $productId): void
    {
        if (null === $productId) {
            return; // La contrainte de validation NotNull s'en charge déjà.
        }

        try {
            $exists = $this->catalogueClient->productExists($productId);
        } catch (TransportExceptionInterface) {
            throw new HttpException(
                502,
                sprintf('Le service Catalogue est injoignable, impossible de vérifier le produit %d.', $productId),
            );
        }

        if (!$exists) {
            throw new HttpException(
                422,
                sprintf('Produit inexistant : aucun produit %d dans le Catalogue.', $productId),
            );
        }
    }
}
