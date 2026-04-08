<?php

namespace App\Repository;

use App\Entity\Services;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Services>
 */
class ServicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Services::class);
    }

  
    public function findBySearch(string $query): array
    {
        $q = '%' . strtolower($query) . '%';

        return $this->createQueryBuilder('s')
            ->where('LOWER(s.nom) LIKE :q')
            ->orWhere('LOWER(s.description) LIKE :q')
            ->orWhere('LOWER(s.localisation) LIKE :q')
            ->orWhere('LOWER(s.ville_depart) LIKE :q')
            ->orWhere('LOWER(s.ville_arrivee) LIKE :q')
            ->setParameter('q', $q)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

  
    public function findAvailableByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.type = :type')
            ->andWhere('s.disponibilite = :dispo')
            ->setParameter('type', $type)
            ->setParameter('dispo', '1')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
