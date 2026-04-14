<?php

namespace App\Repository;

use App\Entity\Messages;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Messages>
 */
class MessagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Messages::class);
    }

    /**
     * Replaces 'selectByConversation'
     */
    public function findByConversation(int $idConversation): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.idConversation = :id')
            ->andWhere('m.isDeleted = false')
            ->setParameter('id', $idConversation)
            ->orderBy('m.dateEnvoi', 'ASC') // Oldest to newest for chat flow
            ->getQuery()
            ->getResult();
    }

    /**
     * Replaces 'selectLastMessage'
     */
    public function findLastMessage(int $idConversation): ?Messages
    {
        return $this->createQueryBuilder('m')
            ->where('m.idConversation = :id')
            ->setParameter('id', $idConversation)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Replaces 'MarquerCommeLu'
     * Marks all messages received by the current user in this conversation as read.
     */
    public function markAllAsRead(int $idConversation, int $currentUserId)
    {
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.lu', 'true')
            ->where('m.idConversation = :idConv')
            ->andWhere('m.idExpediteur != :userId') // Only mark messages sent by OTHERS
            ->andWhere('m.lu = false')
            ->setParameter('idConv', $idConversation)
            ->setParameter('userId', $currentUserId)
            ->getQuery()
            ->execute();
    }
    public function countUnread(int $idConversation, int $currentUserId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.idConversation = :idConv')
            ->andWhere('m.idExpediteur != :userId')
            ->andWhere('m.lu = false')
            ->andWhere('m.isDeleted = false')
            ->setParameter('idConv', $idConversation)
            ->setParameter('userId', $currentUserId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
