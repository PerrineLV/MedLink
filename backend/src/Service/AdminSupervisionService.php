<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

final class AdminSupervisionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FailedLoginAttemptRepository $failedLoginAttemptRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return array{
     *     health: array{status: string},
     *     failedLoginAttempts: array{last24h: int},
     *     activeAccountsByRole: array{patient: int, aidant: int, soignant: int},
     * }
     */
    public function getHealthSummary(): array
    {
        return [
            'health' => ['status' => $this->databaseStatus()],
            'failedLoginAttempts' => ['last24h' => $this->countFailedLoginAttemptsLast24h()],
            'activeAccountsByRole' => [
                'patient' => $this->userRepository->countActiveByRole(User::ROLE_PATIENT),
                'aidant' => $this->userRepository->countActiveByRole(User::ROLE_AIDANT),
                'soignant' => $this->userRepository->countActiveByRole(User::ROLE_SOIGNANT),
            ],
        ];
    }

    private function databaseStatus(): string
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return 'ok';
        } catch (DbalException) {
            return 'down';
        }
    }

    private function countFailedLoginAttemptsLast24h(): int
    {
        return $this->failedLoginAttemptRepository->countSince(new \DateTimeImmutable('-24 hours'));
    }
}
