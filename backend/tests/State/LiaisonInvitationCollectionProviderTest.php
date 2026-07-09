<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\State\LiaisonInvitationCollectionProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class LiaisonInvitationCollectionProviderTest extends TestCase
{
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private LiaisonInvitationCollectionProvider $provider;

    protected function setUp(): void
    {
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);
        $this->provider = new LiaisonInvitationCollectionProvider(
            $this->patientAidantRepository,
            $this->patientSoignantRepository,
            $this->security,
        );
    }

    public function testReturnsActiveAndPendingLinksOnlyForTheCurrentPatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $soignant = $this->makeUser(3, User::ROLE_SOIGNANT);

        $activeAidant = $this->makeAidantRelation($patient, $aidant, 5);
        $activeAidant->setActive(true);
        $pendingSoignant = $this->makeSoignantRelation($patient, $soignant, 7);

        $this->security->method('getUser')->willReturn($patient);
        // findVisibleForPatient() is expected to already exclude revoked
        // links (revokedAt IS NULL) — the provider trusts the repository.
        $this->patientAidantRepository->method('findVisibleForPatient')->willReturn([$activeAidant]);
        $this->patientSoignantRepository->method('findVisibleForPatient')->willReturn([$pendingSoignant]);

        $invitations = $this->provider->provide(new GetCollection());

        self::assertCount(2, $invitations);
        $ids = array_map(static fn ($invitation) => $invitation->id, $invitations);
        self::assertContains('aidant-5', $ids);
        self::assertContains('soignant-7', $ids);
    }

    public function testReturnsEmptyArrayWhenNoUserIsAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        self::assertSame([], $this->provider->provide(new GetCollection()));
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function makeAidantRelation(User $patient, User $aidant, int $id): PatientAidant
    {
        $relation = new PatientAidant($patient, $aidant);
        $relation->setActive(false);

        (new \ReflectionProperty(PatientAidant::class, 'id'))->setValue($relation, $id);

        return $relation;
    }

    private function makeSoignantRelation(User $patient, User $soignant, int $id): PatientSoignant
    {
        $relation = new PatientSoignant($patient, $soignant);
        $relation->setActive(false);

        (new \ReflectionProperty(PatientSoignant::class, 'id'))->setValue($relation, $id);

        return $relation;
    }
}
