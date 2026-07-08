<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Treatment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Treatment>
 */
class TreatmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Treatment::class);
    }

    /**
     * @param list<int> $patientIds
     *
     * @return list<Treatment>
     */
    public function findActiveByPatientIds(array $patientIds): array
    {
        if ([] === $patientIds) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->andWhere('t.patient IN (:patientIds)')
            ->andWhere('t.active = true')
            ->setParameter('patientIds', $patientIds)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
