<?php

namespace App\Repository;

use App\Entity\Reservations;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservations>
 */
class ReservationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservations::class);
    }

    /**
     * Returns the most recent N reservations (for the dashboard overview).
     * Mirrors the reservation table in DashboardServices.fxml.
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.date_reservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search reservations by client name or payment method.
     * Mirrors: handleSearch in ReservationsController (JavaFX).
     */
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

    /**
     * Returns all taken seat numbers for a given service (vol).
     * Used to build the seat map in ReservationController::buildSeatMap().
     */
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
