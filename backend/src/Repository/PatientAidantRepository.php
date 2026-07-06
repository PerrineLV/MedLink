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
}
