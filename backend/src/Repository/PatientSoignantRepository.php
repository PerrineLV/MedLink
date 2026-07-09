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

    /**
     * @param list<int> $patientIds
     *
     * @return list<PatientSoignant>
     */
    public function findActiveForPatients(array $patientIds): array
    {
        if ([] === $patientIds) {
            return [];
        }

        return $this->createQueryBuilder('ps')
            ->andWhere('ps.patient IN (:patientIds)')
            ->andWhere('ps.active = true')
            ->setParameter('patientIds', $patientIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PatientSoignant>
     */
    public function findVisibleForPatient(User $patient): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.patient = :patient')
            ->andWhere('ps.revokedAt IS NULL')
            ->setParameter('patient', $patient)
            ->orderBy('ps.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PatientSoignant>
     */
    public function findPendingForSoignant(User $soignant): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.soignant = :soignant')
            ->andWhere('ps.active = false')
            ->andWhere('ps.revokedAt IS NULL')
            ->setParameter('soignant', $soignant)
            ->orderBy('ps.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
