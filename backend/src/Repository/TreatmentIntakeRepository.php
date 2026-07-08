<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
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

    public function findOneByScheduleAndDate(TreatmentSchedule $schedule, \DateTimeImmutable $date): ?TreatmentIntake
    {
        return $this->createQueryBuilder('ti')
            ->andWhere('ti.schedule = :schedule')
            ->andWhere('ti.date = :date')
            ->setParameter('schedule', $schedule)
            ->setParameter('date', $date, 'date_immutable')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
