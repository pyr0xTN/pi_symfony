<?php

namespace App\Repository;

use App\Entity\ParticipantConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipantConversation>
 */
class ParticipantConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipantConversation::class);
    }

    /**
     * Replaces 'getParticipantsByConversation'
     */
    public function findActiveParticipants(int $idConversation): array
    {
        return $this->createQueryBuilder('pc')
            ->select('u') // We select the User objects directly
            ->join('pc.idUtilisateur', 'u')
            ->where('pc.idConversation = :id')
            ->andWhere('pc.estActif = true')
            ->setParameter('id', $idConversation)
            ->getQuery()
            ->getResult();
    }

    /**
     * Replaces 'findExistingGroupWithMembers'
     * Logic: Finds a group conversation that has EXACTLY these member IDs.
     */
    public function findGroupWithMembers(array $memberIds): ?int
    {
        $count = count($memberIds);
        
        $qb = $this->createQueryBuilder('pc')
            ->select('IDENTITY(pc.idConversation)')
            ->join('pc.idConversation', 'c')
            ->where('c.type = :type')
            ->andWhere('pc.idUtilisateur IN (:ids)')
            ->groupBy('pc.idConversation')
            ->having('COUNT(pc.idUtilisateur) = :count')
            ->setParameter('type', 'GROUPE')
            ->setParameter('ids', $memberIds)
            ->setParameter('count', $count);

        $result = $qb->getQuery()->getOneOrNullResult();
        
        return $result ? (int) current($result) : null;
    }

    //    /**
    //     * @return ParticipantConversation[] Returns an array of ParticipantConversation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ParticipantConversation
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
