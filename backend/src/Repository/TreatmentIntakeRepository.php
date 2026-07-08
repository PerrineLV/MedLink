<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TreatmentIntake>
 */
class TreatmentIntakeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TreatmentIntake::class);
    }

    public function findOneByTreatmentAndDate(Treatment $treatment, \DateTimeImmutable $date): ?TreatmentIntake
    {
        return $this->createQueryBuilder('ti')
            ->andWhere('ti.treatment = :treatment')
            ->andWhere('ti.date = :date')
            ->setParameter('treatment', $treatment)
            ->setParameter('date', $date, 'date_immutable')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
