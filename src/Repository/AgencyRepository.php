<?php

namespace App\Repository;

use App\Entity\Agency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agency>
 */
class AgencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agency::class);
    }

    /**
     * Attempt login with email + password (plain text comparison — same as Java).
     */
    public function findByCredentials(string $email, string $password): ?Agency
    {
        return $this->createQueryBuilder('a')
            ->where('a.email = :email')
            ->andWhere('a.password = :password')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('password', $password)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
