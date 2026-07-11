<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PatientAidant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PatientAidant>
 */
class PatientAidantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientAidant::class);
    }

    public function hasActiveRelation(User $patient, User $aidant): bool
    {
        return null !== $this->createQueryBuilder('pa')
            ->select('pa.id')
            ->andWhere('pa.patient = :patient')
            ->andWhere('pa.aidant = :aidant')
            ->andWhere('pa.active = true')
            ->setParameter('patient', $patient)
            ->setParameter('aidant', $aidant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<int>
     */
    public function findActivePatientIdsForAidant(User $aidant): array
    {
        $result = $this->createQueryBuilder('pa')
            ->select('IDENTITY(pa.patient) AS patientId')
            ->andWhere('pa.aidant = :aidant')
            ->andWhere('pa.active = true')
            ->setParameter('aidant', $aidant)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['patientId'], $result);
    }

    /**
     * @param list<int> $patientIds
     *
     * @return list<PatientAidant>
     */
    public function findActiveForPatients(array $patientIds): array
    {
        if ([] === $patientIds) {
            return [];
        }

        return $this->createQueryBuilder('pa')
            ->andWhere('pa.patient IN (:patientIds)')
            ->andWhere('pa.active = true')
            ->setParameter('patientIds', $patientIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PatientAidant>
     */
    public function findVisibleForPatient(User $patient): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.patient = :patient')
            ->andWhere('pa.revokedAt IS NULL')
            ->setParameter('patient', $patient)
            ->orderBy('pa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PatientAidant>
     */
    public function findPendingForAidant(User $aidant): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.aidant = :aidant')
            ->andWhere('pa.active = false')
            ->andWhere('pa.revokedAt IS NULL')
            ->setParameter('aidant', $aidant)
            ->orderBy('pa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PatientAidant>
     */
    public function findForAidant(User $aidant): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.aidant = :aidant')
            ->setParameter('aidant', $aidant)
            ->orderBy('pa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
