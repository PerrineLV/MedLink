<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JournalExportController;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\JournalExportPdfService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class JournalExportControllerTest extends TestCase
{
    private Security&Stub $security;
    private UserRepository&Stub $userRepository;
    private JournalEntryRepository&Stub $journalEntryRepository;
    private Environment&Stub $twig;
    private JournalExportController $controller;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->journalEntryRepository = $this->createStub(JournalEntryRepository::class);
        $this->twig = $this->createStub(Environment::class);

        $journalExportPdfService = new JournalExportPdfService($this->journalEntryRepository, $this->twig);
        $this->controller = new JournalExportController($this->security, $this->userRepository, $journalExportPdfService);
    }

    public function testReturnsAPdfResponseForAPatientExportingTheirOwnJournal(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80', 'RAS');

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);
        $this->journalEntryRepository->method('findByPatientAndPeriod')->willReturn([$entry]);
        $this->twig->method('render')->willReturn('<html><body>Suivi</body></html>');

        $response = ($this->controller)(new Request(['from' => '2025-01-01', 'to' => '2025-01-31']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('medlink_suivi_', $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function testIgnoresThePatientQueryParamWhenCallerIsAPatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $otherPatient = $this->makeUser(2, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);

        $capturedPatient = null;
        $this->journalEntryRepository->method('findByPatientAndPeriod')->willReturnCallback(
            function (User $p) use (&$capturedPatient, $entry): array {
                $capturedPatient = $p;

                return [$entry];
            },
        );
        $this->twig->method('render')->willReturn('<html><body>Suivi</body></html>');

        ($this->controller)(new Request(['patient' => (string) $otherPatient->getId(), 'from' => '2025-01-01', 'to' => '2025-01-31']));

        self::assertSame($patient, $capturedPatient);
    }

    public function testThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedHttpException::class);

        ($this->controller)(new Request(['from' => '2025-01-01', 'to' => '2025-01-31']));
    }

    public function testThrowsBadRequestWhenPatientParamIsMissingForAnAidant(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($aidant);

        $this->expectException(BadRequestHttpException::class);

        ($this->controller)(new Request(['from' => '2025-01-01', 'to' => '2025-01-31']));
    }

    public function testThrowsNotFoundWhenThePatientParamDoesNotResolveToAPatient(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        ($this->controller)(new Request(['patient' => '999', 'from' => '2025-01-01', 'to' => '2025-01-31']));
    }

    public function testThrowsAccessDeniedWhenNotGrantedOnTheResolvedPatient(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);
        $patient = $this->makeUser(2, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => UserVoter::VIEW === $attribute && false,
        );
        $this->userRepository->method('find')->willReturn($patient);

        $this->expectException(AccessDeniedHttpException::class);

        ($this->controller)(new Request(['patient' => '2', 'from' => '2025-01-01', 'to' => '2025-01-31']));
    }

    public function testThrowsBadRequestWhenFromIsMissing(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);

        $this->expectException(BadRequestHttpException::class);

        ($this->controller)(new Request(['to' => '2025-01-31']));
    }

    public function testThrowsBadRequestWhenTheExportPeriodIsInvalid(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);

        $this->expectException(BadRequestHttpException::class);

        ($this->controller)(new Request(['from' => '2025-01-31', 'to' => '2025-01-01']));
    }

    public function testThrowsBadRequestWhenNoEntryIsFoundOnThePeriod(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);
        $this->journalEntryRepository->method('findByPatientAndPeriod')->willReturn([]);

        $this->expectException(BadRequestHttpException::class);

        ($this->controller)(new Request(['from' => '2025-01-01', 'to' => '2025-01-31']));
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
