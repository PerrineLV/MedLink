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
     * @return list<Treatment>
     */
    public function findActiveByPatient(int $patientId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.patient = :patientId')
            ->andWhere('t.active = true')
            ->setParameter('patientId', $patientId)
            ->orderBy('t.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
