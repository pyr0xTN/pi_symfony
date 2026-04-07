<?php

namespace App\Repository;

use App\Entity\Publication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /**
     * Front office: only APPROVED posts, newest first (with user eager-loaded).
     * @return Publication[]
     */
    public function findAllApproved(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->where('p.status = :status')
            ->setParameter('status', Publication::STATUS_APPROVED)
            ->orderBy('p.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Back-office: all posts for a given agency, ordered by status then date.
     * @return Publication[]
     */
    public function findByAgency(int $agencyId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->where('p.agencyId = :agencyId')
            ->setParameter('agencyId', $agencyId)
            ->orderBy('p.status', 'ASC')
            ->addOrderBy('p.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in content or place.
     * @return Publication[]
     */
    public function searchByKeyword(string $keyword): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->where('p.content LIKE :kw OR p.place LIKE :kw')
            ->andWhere('p.status = :status')
            ->setParameter('kw', '%' . $keyword . '%')
            ->setParameter('status', Publication::STATUS_APPROVED)
            ->orderBy('p.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Posts by a specific user.
     * @return Publication[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All approved posts that have a place set (for map markers).
     * @return Publication[]
     */
    public function findWithPlaces(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->where('p.place IS NOT NULL AND p.place != :empty')
            ->andWhere('p.status = :status')
            ->setParameter('empty', '')
            ->setParameter('status', Publication::STATUS_APPROVED)
            ->orderBy('p.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
