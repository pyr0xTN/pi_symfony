<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Get comments for a publication, chronological, with user data.
     * @return Comment[]
     */
    public function findByPublication(int $publicationId): array
    {
        return $this->createQueryBuilder('cm')
            ->leftJoin('cm.user', 'u')->addSelect('u')
            ->where('cm.publication = :pubId')
            ->setParameter('pubId', $publicationId)
            ->orderBy('cm.commentDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByPublication(int $publicationId): int
    {
        return (int) $this->createQueryBuilder('cm')
            ->select('COUNT(cm.id)')
            ->where('cm.publication = :pubId')
            ->setParameter('pubId', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
