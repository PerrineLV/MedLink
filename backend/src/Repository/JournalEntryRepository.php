<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JournalEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalEntry>
 */
class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntry::class);
    }

    /**
     * @param list<int> $patientIds
     *
     * @return list<JournalEntry>
     */
    public function findByPatientIds(array $patientIds): array
    {
        if ([] === $patientIds) {
            return [];
        }

        return $this->createQueryBuilder('je')
            ->andWhere('je.patient IN (:patientIds)')
            ->setParameter('patientIds', $patientIds)
            ->orderBy('je.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<JournalEntry>
     */
    public function findByPatientAndPeriod(User $patient, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('je')
            ->andWhere('je.patient = :patient')
            ->andWhere('je.createdAt BETWEEN :from AND :to')
            ->setParameter('patient', $patient)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('je.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
