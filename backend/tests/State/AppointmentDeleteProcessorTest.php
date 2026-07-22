<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\PatientSoignantRepository;
use App\Service\AppointmentService;
use App\State\AppointmentDeleteProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AppointmentDeleteProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private AppointmentDeleteProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);

        $appointmentService = new AppointmentService($this->entityManager, $this->patientSoignantRepository, $this->security);

        $this->processor = new AppointmentDeleteProcessor($appointmentService);
    }

    public function testDeletesTheAppointmentForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+1 day'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::once())->method('remove')->with($appointment);
        $this->entityManager->expects(self::once())->method('flush');

        $this->processor->process($appointment, new Delete());
    }

    public function testThrowsNotFoundWhenDataIsNotAnAppointment(): void
    {
        $this->entityManager->expects(self::never())->method('remove');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Delete());
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
