<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use App\Repository\TreatmentIntakeRepository;
use App\Service\TreatmentIntakeService;
use App\State\TreatmentIntakeToggleProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TreatmentIntakeToggleProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TreatmentIntakeRepository&Stub $treatmentIntakeRepository;
    private TreatmentIntakeToggleProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->treatmentIntakeRepository = $this->createStub(TreatmentIntakeRepository::class);

        $treatmentIntakeService = new TreatmentIntakeService($this->entityManager, $this->treatmentIntakeRepository);

        $this->processor = new TreatmentIntakeToggleProcessor($treatmentIntakeService);
    }

    public function testTogglesTheIntakeFromReadData(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $treatment = new Treatment($user, 'Doliprane', '1000mg', $user);
        $schedule = new TreatmentSchedule($treatment, TreatmentSchedule::MOMENT_MORNING);
        $intake = new TreatmentIntake($schedule, new \DateTimeImmutable());

        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process(null, new Patch(), [], ['read_data' => $intake]);

        self::assertSame($intake, $result);
        self::assertTrue($result->isTaken());
    }

    public function testThrowsNotFoundWhenReadDataIsNotATreatmentIntake(): void
    {
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Patch(), [], ['read_data' => null]);
    }
}
