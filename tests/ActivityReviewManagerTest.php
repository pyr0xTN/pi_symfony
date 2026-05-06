<?php

namespace App\Tests\Service;

use App\Entity\ActivityReview;
use App\Entity\User;

use PHPUnit\Framework\TestCase;
use App\Service\ActivityReviewManager;
use DateTimeImmutable;
use InvalidArgumentException;
class ActivityReviewManagerTest extends TestCase
{
    private ActivityReviewManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ActivityReviewManager();
    }

    /** Cas valide : tout est rempli correctement */
    public function testValidReview(): void
    {
        $user = $this->createMock(User::class);

        $review = new ActivityReview();
        $review->setActivityId(42);
        $review->setUser($user);
        $review->setContent('Super activité, guide très professionnel !');
        $review->setCreatedAt(new DateTimeImmutable());

        $this->assertTrue($this->manager->validate($review));
    }

    /** Règle 1 : contenu vide → exception */
    public function testEmptyContentThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu de l avis est obligatoire');

        $user = $this->createMock(User::class);
        $review = new ActivityReview();
        $review->setActivityId(42);
        $review->setUser($user);
        $review->setContent('');

        $this->manager->validate($review);
    }

    /** Règle 1 bis : contenu avec espaces seulement */
    public function testWhitespaceOnlyContentThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = $this->createMock(User::class);
        $review = new ActivityReview();
        $review->setActivityId(42);
        $review->setUser($user);
        $review->setContent('     ');

        $this->manager->validate($review);
    }

    /** Règle 2 : activityId invalide → exception */
    public function testInvalidActivityIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L identifiant de l activité est invalide');

        $user = $this->createMock(User::class);
        $review = new ActivityReview();
        $review->setActivityId(0);
        $review->setUser($user);
        $review->setContent('Très bonne activité !');

        $this->manager->validate($review);
    }

    /** Règle 3 : utilisateur null → exception */
    public function testNullUserThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L avis doit être associé à un utilisateur');

        $review = new ActivityReview();
        $review->setActivityId(42);
        $review->setUser(null);
        $review->setContent('Bonne activité !');

        $this->manager->validate($review);
    }
}