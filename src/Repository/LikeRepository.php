<?php

namespace App\Repository;

use App\Entity\Like;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Like>
 */
class LikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    public function countByPublication(int $publicationId): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.publication = :pubId')
            ->setParameter('pubId', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUserLike(int $publicationId, int $clientId): ?Like
    {
        return $this->createQueryBuilder('l')
            ->where('l.publication = :pubId')
            ->andWhere('l.client = :clientId')
            ->setParameter('pubId', $publicationId)
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasUserLiked(int $publicationId, int $clientId): bool
    {
        return $this->findUserLike($publicationId, $clientId) !== null;
    }
}
