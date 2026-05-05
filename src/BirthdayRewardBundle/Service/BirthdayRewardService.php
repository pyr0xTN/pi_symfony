<?php

namespace App\BirthdayRewardBundle\Service;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class BirthdayRewardService
{
    private const REWARD_COINS = 7;

    public function buildBirthdayGiftState(User $user, SessionInterface $session, Connection $connection): array
    {
        if ($user->getId() === null || !($user->getDate() instanceof \DateTimeInterface)) {
            return $this->defaultState();
        }

        $now = new DateTimeImmutable('now');
        $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());

        $isBirthdayToday = $this->isBirthdayToday($user, $now);
        $currentYear = (int) $now->format('Y');
        $storedBirthdayGiftYear = (int) ($profile['birthday_gift_year'] ?? 0);
        $isGiftAvailable = $isBirthdayToday && $storedBirthdayGiftYear !== $currentYear;

        if ($isBirthdayToday) {
            $this->syncBirthdayGiftFlag($connection, (int) $profile['id'], $isGiftAvailable ? 1 : $currentYear);
        }

        return [
            'showBanner' => $isGiftAvailable,
            'canCollect' => $isGiftAvailable,
            'isBirthdayToday' => $isBirthdayToday,
            'claimedYear' => $storedBirthdayGiftYear,
            'currentYear' => $currentYear,
            'reward' => self::REWARD_COINS,
            'coins' => (int) ($profile['coins'] ?? 0),
            'giftLabel' => $isGiftAvailable ? 'Collect this gift' : 'Gift collected',
            'message' => $isGiftAvailable
                ? 'Collect 7 coins for your birthday and enjoy the day. This gift is available now.'
                : 'You already collected your birthday gift.',
            'birthdayLabel' => $user->getDate()->format('F j'),
            'name' => trim((string) $user->getFullName()),
        ];
    }

    public function collectBirthdayGift(User $user, SessionInterface $session, Connection $connection): array
    {
        if ($user->getId() === null || !($user->getDate() instanceof \DateTimeInterface)) {
            return [
                'success' => false,
                'message' => 'Birthday gift is unavailable.',
            ];
        }

        $now = new DateTimeImmutable('now');
        if (!$this->isBirthdayToday($user, $now)) {
            return [
                'success' => false,
                'message' => 'This gift is not available right now.',
            ];
        }

        $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
        if (!$profile || !isset($profile['id'])) {
            return [
                'success' => false,
                'message' => 'Profile not found.',
            ];
        }

        $currentYear = (int) $now->format('Y');
        $availableFlag = (int) ($profile['birthday_gift_year'] ?? 0);
        if ($availableFlag === $currentYear) {
            return [
                'success' => false,
                'message' => 'This gift is not available right now.',
            ];
        }

        $connection->executeStatement(
            'UPDATE profile SET coins = COALESCE(coins, 0) + ?, birthday_gift_year = ? WHERE id = ?',
            [self::REWARD_COINS, $currentYear, (int) $profile['id']],
            [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER]
        );

        $newCoins = (int) $connection->fetchOne(
            'SELECT coins FROM profile WHERE id = ? LIMIT 1',
            [(int) $profile['id']],
            [ParameterType::INTEGER]
        );

        return [
            'success' => true,
            'message' => 'Happy birthday! Your gift has been added.',
            'awarded' => self::REWARD_COINS,
            'coins' => $newCoins,
            'claimedYear' => $currentYear,
        ];
    }

    private function syncBirthdayGiftFlag(Connection $connection, int $profileId, int $flag): void
    {
        $connection->executeStatement(
            'UPDATE profile SET birthday_gift_year = ? WHERE id = ?',
            [$flag, $profileId],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
    }

    private function defaultState(): array
    {
        return [
            'showBanner' => false,
            'canCollect' => false,
            'isBirthdayToday' => false,
            'claimedYear' => null,
            'currentYear' => (int) (new DateTimeImmutable('now'))->format('Y'),
            'reward' => self::REWARD_COINS,
            'coins' => 0,
            'giftLabel' => 'Collect this gift',
            'message' => '',
            'birthdayLabel' => '',
            'name' => '',
        ];
    }

    private function isBirthdayToday(User $user, DateTimeImmutable $now): bool
    {
        return $user->getDate() instanceof \DateTimeInterface
            && $user->getDate()->format('m-d') === $now->format('m-d');
    }

    private function fetchOrCreateProfileRow(Connection $connection, int $userId): ?array
    {
        $row = $connection->executeQuery(
            'SELECT id, coins, birthday_gift_year FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [$userId],
            [ParameterType::INTEGER]
        )->fetchAssociative();

        if ($row) {
            return $row;
        }

        $connection->executeStatement(
            'INSERT INTO profile (image, member_premium, language, id_user, coins, birthday_gift_year) VALUES (?, ?, ?, ?, ?, 0)',
            [null, 'standard', 'English', $userId, 0],
            [ParameterType::LARGE_OBJECT, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER]
        );

        return $connection->executeQuery(
            'SELECT id, coins, birthday_gift_year FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [$userId],
            [ParameterType::INTEGER]
        )->fetchAssociative() ?: null;
    }
}