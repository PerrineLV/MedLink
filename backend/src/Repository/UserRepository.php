<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Paginated admin listing (ML-53), optionally filtered by a full role
     * string (e.g. User::ROLE_PATIENT) and/or by active status.
     *
     * `roles` is stored as plain Postgres `json` (not `jsonb`), which has no
     * LIKE/text operator of its own, so the role filter is resolved as a
     * raw-SQL id lookup first and then applied to the DQL query as an
     * `id IN (...)` restriction.
     *
     * @return array{items: list<User>, total: int}
     */
    public function search(?string $role, ?bool $active, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('u');

        if (null !== $role) {
            $matchingIds = $this->findIdsByRole($role);
            if ([] === $matchingIds) {
                return ['items' => [], 'total' => 0];
            }

            $qb->andWhere('u.id IN (:ids)')->setParameter('ids', $matchingIds);
        }

        if (null !== $active) {
            $qb->andWhere('u.active = :active')->setParameter('active', $active);
        }

        // The ORDER BY is added only after cloning for the count query: Postgres
        // rejects ORDER BY u.created_at alongside a bare COUNT() aggregate.
        $total = (int) (clone $qb)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Number of active accounts holding the given role (ML-55 admin
     * supervision screen). Same raw-SQL role match as {@see search()}, but
     * counted directly rather than through an id lookup + DQL round trip.
     */
    public function countActiveByRole(string $role): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM "user" WHERE active = true AND roles::text LIKE :pattern',
            ['pattern' => '%"'.$role.'"%'],
        );
    }

    /**
     * @return list<int>
     */
    private function findIdsByRole(string $role): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM "user" WHERE roles::text LIKE :pattern',
            ['pattern' => '%"'.$role.'"%'],
        );

        return array_map('intval', $ids);
    }
}
