<?php

namespace App\DataFixtures;

use App\Entity\Review;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu d'avis de démonstration.
 *
 * Chargement : php bin/console doctrine:fixtures:load
 * (suppose que les produits 1 et 2 existent dans le Catalogue).
 */
class ReviewFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $samples = [
            ['rating' => 5, 'comment' => 'Excellent produit, je recommande vivement !', 'productId' => 1, 'author' => 'Alice'],
            ['rating' => 4, 'comment' => 'Très bon rapport qualité-prix, livraison rapide.', 'productId' => 1, 'author' => 'Bob'],
            ['rating' => 2, 'comment' => 'Déçu par la finition, mais le SAV est réactif.', 'productId' => 2, 'author' => 'Charlie'],
        ];

        foreach ($samples as $s) {
            $review = (new Review())
                ->setRating($s['rating'])
                ->setComment($s['comment'])
                ->setProductId($s['productId'])
                ->setAuthor($s['author']);

            $manager->persist($review);
        }

        $manager->flush();
    }
}
