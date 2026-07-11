<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FailedLoginAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per failed login attempt (ML-55 admin supervision). Deliberately
 * carries no personal data (no email, no IP) — consistent with the
 * project-wide rule that logs never contain personal data (A09) — so it
 * only ever answers "how many", never "who" or "from where".
 */
#[ORM\Entity(repositoryClass: FailedLoginAttemptRepository::class)]
class FailedLoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
