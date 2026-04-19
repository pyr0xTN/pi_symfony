<?php

namespace App\Repository;

use App\Entity\Services;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function findAllQueryBuilder(string $search = ''): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if ($search) {
            $q = '%' . strtolower($search) . '%';
            $qb->where('LOWER(s.nom) LIKE :q')
               ->orWhere('LOWER(s.description) LIKE :q')
               ->orWhere('LOWER(s.localisation) LIKE :q')
               ->setParameter('q', $q);
        }

        return $qb->orderBy('s.nom', 'ASC');
    }

    public function findWithAiQueryBuilder(array $filters): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if (!empty($filters['type'])) {
            $qb->andWhere('s.type = :type')
               ->setParameter('type', strtolower($filters['type']));
        }

        if (!empty($filters['localisation'])) {
            $qb->andWhere('LOWER(s.localisation) LIKE :loc OR LOWER(s.ville_arrivee) LIKE :loc')
               ->setParameter('loc', '%' . strtolower($filters['localisation']) . '%');
        }

        if (!empty($filters['ville_depart'])) {
            $qb->andWhere('LOWER(s.ville_depart) LIKE :vdepart')
               ->setParameter('vdepart', '%' . strtolower($filters['ville_depart']) . '%');
        }

        if (!empty($filters['ville_arrivee'])) {
            $qb->andWhere('LOWER(s.ville_arrivee) LIKE :varrivee OR LOWER(s.localisation) LIKE :varrivee')
               ->setParameter('varrivee', '%' . strtolower($filters['ville_arrivee']) . '%');
        }

        if (!empty($filters['nombre_etoiles'])) {
            $qb->andWhere('s.nombre_etoiles >= :etoiles')
               ->setParameter('etoiles', (int) $filters['nombre_etoiles']);
        }

        if (!empty($filters['max_prix'])) {
            $qb->andWhere('s.prix <= :max_prix')
               ->setParameter('max_prix', (float) $filters['max_prix']);
        }

        return $qb->orderBy('s.nom', 'ASC');
    }
}
