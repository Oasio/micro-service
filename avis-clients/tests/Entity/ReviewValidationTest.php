<?php

namespace App\Tests\Entity;

use App\Entity\Review;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Test unitaire (KernelTestCase) des contraintes de validation de Review (étape 5/8).
 */
class ReviewValidationTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return static::getContainer()->get(ValidatorInterface::class);
    }

    private function validReview(): Review
    {
        return (new Review())
            ->setRating(5)
            ->setComment('Un commentaire valide et suffisamment long.')
            ->setProductId(1)
            ->setAuthor('Alice');
    }

    public function testValidReviewHasNoViolation(): void
    {
        $violations = $this->validator()->validate($this->validReview());

        $this->assertCount(0, $violations);
    }

    public function testRatingOutOfRangeIsRejected(): void
    {
        $review = $this->validReview()->setRating(6);

        $violations = $this->validator()->validate($review);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function testBlankCommentIsRejected(): void
    {
        $review = $this->validReview()->setComment('');

        $violations = $this->validator()->validate($review);

        $this->assertGreaterThan(0, $violations->count());
    }
}
