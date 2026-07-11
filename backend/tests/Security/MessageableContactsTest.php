<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Repository\UserRepository;
use App\Security\MessageableContacts;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class MessageableContactsTest extends TestCase
{
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private UserRepository&Stub $userRepository;
    private MessageableContacts $messageableContacts;

    protected function setUp(): void
    {
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->messageableContacts = new MessageableContacts(
            $this->patientAidantRepository,
            $this->patientSoignantRepository,
            $this->userRepository,
        );
    }

    public function testPatientGetsTheirActiveSoignantWithoutViaPatients(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->patientSoignantRepository->method('findActiveForPatients')
            ->willReturnCallback(fn (array $patientIds): array => [1] === $patientIds ? [new PatientSoignant($patient, $soignant)] : []);

        $contacts = $this->messageableContacts->forUser($patient);

        self::assertCount(1, $contacts);
        self::assertSame($soignant, $contacts[0]->user);
        self::assertSame(User::ROLE_SOIGNANT, $contacts[0]->role);
        // Le patient contacte son propre soignant : pas d'ambiguïté, donc
        // pas besoin de préciser "via quel patient".
        self::assertSame([], $contacts[0]->viaPatients);
    }

    public function testAidantGetsOneContactWithBothSharedPatientsWhenTheSameSoignantServesBoth(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);
        $patientA = $this->makeUser(2, User::ROLE_PATIENT);
        $patientB = $this->makeUser(3, User::ROLE_PATIENT);
        $soignant = $this->makeUser(4, User::ROLE_SOIGNANT);

        $this->patientAidantRepository->method('findActivePatientIdsForAidant')->willReturn([2, 3]);
        $this->patientSoignantRepository->method('findActiveForPatients')
            ->willReturnCallback(fn (array $patientIds): array => [2, 3] === $patientIds
                ? [new PatientSoignant($patientA, $soignant), new PatientSoignant($patientB, $soignant)]
                : []);

        $contacts = $this->messageableContacts->forUser($aidant);

        self::assertCount(1, $contacts);
        self::assertSame($soignant, $contacts[0]->user);
        self::assertSame(User::ROLE_SOIGNANT, $contacts[0]->role);
        self::assertSame([$patientA, $patientB], $contacts[0]->viaPatients);
    }

    public function testAidantGetsSeparateContactsForDifferentSoignants(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);
        $patientA = $this->makeUser(2, User::ROLE_PATIENT);
        $patientB = $this->makeUser(3, User::ROLE_PATIENT);
        $soignant1 = $this->makeUser(4, User::ROLE_SOIGNANT);
        $soignant2 = $this->makeUser(5, User::ROLE_SOIGNANT);

        $this->patientAidantRepository->method('findActivePatientIdsForAidant')->willReturn([2, 3]);
        $this->patientSoignantRepository->method('findActiveForPatients')->willReturn([
            new PatientSoignant($patientA, $soignant1),
            new PatientSoignant($patientB, $soignant2),
        ]);

        $contacts = $this->messageableContacts->forUser($aidant);

        self::assertCount(2, $contacts);
        self::assertSame($soignant1, $contacts[0]->user);
        self::assertSame([$patientA], $contacts[0]->viaPatients);
        self::assertSame($soignant2, $contacts[1]->user);
        self::assertSame([$patientB], $contacts[1]->viaPatients);
    }

    public function testSoignantGetsTheirPatientsAndTheAidantsOfThosePatientsWithViaPatients(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);
        $patientA = $this->makeUser(2, User::ROLE_PATIENT);
        $aidant = $this->makeUser(3, User::ROLE_AIDANT);

        $this->patientSoignantRepository->method('findActivePatientIdsForSoignant')->willReturn([2]);
        $this->userRepository->method('findBy')
            ->willReturnCallback(fn (array $criteria): array => [2] === $criteria['id'] ? [$patientA] : []);
        $this->patientAidantRepository->method('findActiveForPatients')
            ->willReturnCallback(fn (array $patientIds): array => [2] === $patientIds ? [new PatientAidant($patientA, $aidant)] : []);

        $contacts = $this->messageableContacts->forUser($soignant);

        self::assertCount(2, $contacts);
        self::assertSame($patientA, $contacts[0]->user);
        self::assertSame(User::ROLE_PATIENT, $contacts[0]->role);
        self::assertSame([], $contacts[0]->viaPatients);
        self::assertSame($aidant, $contacts[1]->user);
        self::assertSame(User::ROLE_AIDANT, $contacts[1]->role);
        self::assertSame([$patientA], $contacts[1]->viaPatients);
    }

    public function testSoignantWithNoActivePatientsGetsNoContactsAndSkipsTheUserLookup(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);

        $this->patientSoignantRepository->method('findActivePatientIdsForSoignant')->willReturn([]);
        $this->patientAidantRepository->method('findActiveForPatients')->willReturn([]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findBy');
        $messageableContacts = new MessageableContacts(
            $this->patientAidantRepository,
            $this->patientSoignantRepository,
            $userRepository,
        );

        self::assertSame([], $messageableContacts->forUser($soignant));
    }

    public function testAdminGetsNoContacts(): void
    {
        $admin = $this->makeUser(1, User::ROLE_ADMIN);

        self::assertSame([], $this->messageableContacts->forUser($admin));
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
