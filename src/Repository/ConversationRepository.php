<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use App\Enum\TypeConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findConversationsByUser(int $userId): array
    {
        return $this->createQueryBuilder('c')
            // On sélectionne la date max des messages pour trier
            ->addSelect('MAX(m.dateEnvoi) as HIDDEN lastMsgDate')
            ->innerJoin('c.participants', 'p')
            ->leftJoin('c.messages', 'm')
            ->where('IDENTITY(p.idUtilisateur) = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('c.id')
            // Tri : Dernier message d'abord, puis date de création si pas de message
            ->orderBy('lastMsgDate', 'DESC')
            ->addOrderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPrivateChat(int $userId, int $recipientId): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('c.type = :type')
            ->andWhere('IDENTITY(p.idUtilisateur) IN (:ids)')
            ->setParameter('type', TypeConversation::PRIVEE)
            ->setParameter('ids', [$userId, $recipientId])
            ->groupBy('c.id')
            ->having('COUNT(p.id) = 2')
            ->getQuery()
            ->getOneOrNullResult();
    }


    //    /**
    //     * @return Conversation[] Returns an array of Conversation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Conversation
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
