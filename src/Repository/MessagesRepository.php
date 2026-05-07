<?php

namespace App\Repository;

use App\Entity\Messages;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Conversation;

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

    public function findMessagesBeforeDate(Conversation $conversation, \DateTimeInterface $dateSortie)
    {
        return $this->createQueryBuilder('m')
            ->where('m.idConversation = :conv')
            ->andWhere('m.dateEnvoi <= :dateLimit') // On prend tout ce qui est AVANT ou ÉGAL à la sortie
            ->setParameter('conv', $conversation)
            ->setParameter('dateLimit', $dateSortie)
            ->orderBy('m.dateEnvoi', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // src/Repository/MessagesRepository.php

    public function findLastMessageBeforeDate(Conversation $conversation, \DateTimeInterface $dateLimit)
    {
        return $this->createQueryBuilder('m')
            ->where('m.idConversation = :conv')
            ->andWhere('m.dateEnvoi <= :limit')
            ->setParameter('conv', $conversation)
            ->setParameter('limit', $dateLimit)
            ->orderBy('m.dateEnvoi', 'DESC') // On prend le plus récent...
            ->setMaxResults(1)               // ...mais un seul
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByMessageType(): array
    {
        $results = $this->createQueryBuilder('m')
            ->select('m.typeMessage, COUNT(m.id) as count')
            ->groupBy('m.typeMessage')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $r) {
            $stats[$r['typeMessage']->value] = $r['count'];
        }
        return $stats;
    }

    public function getMessagesLast7Days(): array
    {
        $date = new \DateTime('-7 days');
        return $this->createQueryBuilder('m')
            ->select('SUBSTRING(m.dateEnvoi, 1, 10) as day, COUNT(m.id) as count')
            ->where('m.dateEnvoi >= :date')
            ->setParameter('date', $date)
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
