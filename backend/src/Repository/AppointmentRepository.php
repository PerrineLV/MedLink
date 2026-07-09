<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * @param list<int> $patientIds
     *
     * @return list<Appointment>
     */
    public function findByPatientIds(array $patientIds): array
    {
        if ([] === $patientIds) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.patient IN (:patientIds)')
            ->setParameter('patientIds', $patientIds)
            ->orderBy('a.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
