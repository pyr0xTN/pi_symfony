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
    public function getRevenueLast7Days(): array
    {
        $date = new \DateTime('-7 days');
        
        return $this->createQueryBuilder('r')
            ->select('r.date_reservation as date, SUM(s.prix * r.seat_nb) as revenue')
            ->join('r.idService', 's')
            ->where('r.date_reservation >= :date')
            ->setParameter('date', $date)
            ->groupBy('r.date_reservation')
            ->orderBy('r.date_reservation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRevenueByType(): array
    {
        return $this->createQueryBuilder('r')
            ->select('s.type, SUM(s.prix * r.seat_nb) as revenue')
            ->join('r.idService', 's')
            ->groupBy('s.type')
            ->getQuery()
            ->getResult();
    }

    public function findAllQueryBuilder(string $search = ''): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');

        if ($search) {
            $q = '%' . strtolower($search) . '%';
            $qb->where('LOWER(r.nom) LIKE :q')
               ->orWhere('LOWER(r.mode_paiement) LIKE :q')
               ->setParameter('q', $q);
        }

        return $qb->orderBy('r.date_reservation', 'DESC');
    }

    public function findActiveByNom(string $nom): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.idService', 's')
            ->addSelect('s')
            ->where('LOWER(r.nom) = :nom')
            ->andWhere('LOWER(r.statut) != :cancelled')
            ->setParameter('nom', strtolower($nom))
            ->setParameter('cancelled', 'annulée')
            ->getQuery()
            ->getResult();
    }
}
