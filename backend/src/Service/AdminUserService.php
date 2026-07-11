<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdminUserService
{
    private const ROLE_FILTERS = [
        'patient' => User::ROLE_PATIENT,
        'aidant' => User::ROLE_AIDANT,
        'soignant' => User::ROLE_SOIGNANT,
    ];

    private const STATUS_FILTERS = [
        'actif' => true,
        'désactivé' => false,
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{items: list<User>, total: int}
     */
    public function listUsers(?string $role, ?string $status, int $page, int $perPage): array
    {
        return $this->userRepository->search(
            $this->resolveRoleFilter($role),
            $this->resolveStatusFilter($status),
            $page,
            $perPage,
        );
    }

    public function setUserActive(int $id, bool $active): User
    {
        $user = $this->userRepository->find($id);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }

        $user->setActive($active);
        $this->entityManager->flush();

        return $user;
    }

    private function resolveRoleFilter(?string $role): ?string
    {
        if (null === $role) {
            return null;
        }

        if (!isset(self::ROLE_FILTERS[$role])) {
            throw new BadRequestHttpException(sprintf('Filtre "role" invalide : "%s".', $role));
        }

        return self::ROLE_FILTERS[$role];
    }

    private function resolveStatusFilter(?string $status): ?bool
    {
        if (null === $status) {
            return null;
        }

        if (!array_key_exists($status, self::STATUS_FILTERS)) {
            throw new BadRequestHttpException(sprintf('Filtre "status" invalide : "%s".', $status));
        }

        return self::STATUS_FILTERS[$status];
    }
}
