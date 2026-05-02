<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class UserReservationRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function hasUserReservedActivity(int $userId, int $activityId): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (
                SELECT ur.reservation_id
                FROM user_reservation ur
                WHERE ur.user_id = ? AND ur.activity_id = ?
                UNION ALL
                SELECT a.idAchat
                FROM achat a
                WHERE a.idClient = ? AND a.idActivite = ?
            ) AS reserved_activity',
            [$userId, $activityId, $userId, $activityId]
        );

        return $count > 0;
    }
}
