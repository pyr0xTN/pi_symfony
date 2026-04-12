<?php

namespace App\Repository;

use App\Entity\Reservations;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservations::class);
    }

    
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.date_reservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

 
    public function findBySearch(string $query): array
    {
        $q = '%' . strtolower($query) . '%';

        return $this->createQueryBuilder('r')
            ->where('LOWER(r.nom) LIKE :q')
            ->orWhere('LOWER(r.	mode_paiement) LIKE :q')
            ->setParameter('q', $q)
            ->orderBy('r.date_reservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    
    public function findTakenSeatsByService(int $serviceId): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.seatNb')
            ->where('r.idService = :sid')
            ->setParameter('sid', $serviceId)
            ->getQuery()
            ->getSingleColumnResult();
    }
    
}
