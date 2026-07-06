<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PatientSoignant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PatientSoignant>
 */
class PatientSoignantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientSoignant::class);
    }

    public function hasActiveRelation(User $patient, User $soignant): bool
    {
        return null !== $this->createQueryBuilder('ps')
            ->select('ps.id')
            ->andWhere('ps.patient = :patient')
            ->andWhere('ps.soignant = :soignant')
            ->andWhere('ps.active = true')
            ->setParameter('patient', $patient)
            ->setParameter('soignant', $soignant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<int>
     */
    public function findActivePatientIdsForSoignant(User $soignant): array
    {
        $result = $this->createQueryBuilder('ps')
            ->select('IDENTITY(ps.patient) AS patientId')
            ->andWhere('ps.soignant = :soignant')
            ->andWhere('ps.active = true')
            ->setParameter('soignant', $soignant)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['patientId'], $result);
    }
}
