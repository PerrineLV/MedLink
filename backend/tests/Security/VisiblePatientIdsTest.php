<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Security\VisiblePatientIds;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class VisiblePatientIdsTest extends TestCase
{
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private VisiblePatientIds $visiblePatientIds;

    protected function setUp(): void
    {
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->visiblePatientIds = new VisiblePatientIds(
            $this->patientAidantRepository,
            $this->patientSoignantRepository,
        );
    }

    public function testPatientOnlySeesThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        self::assertSame([1], $this->visiblePatientIds->forUser($patient));
    }

    public function testAidantSeesTheirActivelyAttachedPatients(): void
    {
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $this->patientAidantRepository->method('findActivePatientIdsForAidant')->willReturn([1, 3]);

        self::assertSame([1, 3], $this->visiblePatientIds->forUser($aidant));
    }

    public function testSoignantSeesTheirActivelyReferredPatients(): void
    {
        $soignant = $this->makeUser(4, User::ROLE_SOIGNANT);
        $this->patientSoignantRepository->method('findActivePatientIdsForSoignant')->willReturn([1, 2]);

        self::assertSame([1, 2], $this->visiblePatientIds->forUser($soignant));
    }

    public function testAdminSeesNoPatients(): void
    {
        $admin = $this->makeUser(5, User::ROLE_ADMIN);

        self::assertSame([], $this->visiblePatientIds->forUser($admin));
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
