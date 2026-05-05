<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityReview>
 */
final class ActivityReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityReview::class);
    }

    /**
     * @return ActivityReview[]
     */
    public function findByActivityId(int $activityId): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('COALESCE(review.updatedAt, review.createdAt)', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUserReview(int $activityId, int $userId): ?ActivityReview
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.activityId = :activityId')
            ->andWhere('IDENTITY(review.user) = :userId')
            ->setParameter('activityId', $activityId)
            ->setParameter('userId', $userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
