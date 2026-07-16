<?php

declare(strict_types=1);

namespace App\Tests\ApiResource;

use App\ApiResource\LiaisonInvitation;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class LiaisonInvitationTest extends TestCase
{
    public function testForSoignantRelationPropagatesTheSoignantTitle(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $soignant->setTitle('Dr');
        $relation = $this->makeSoignantRelation($patient, $soignant, 7);

        $invitation = LiaisonInvitation::forSoignantRelation($relation);

        self::assertSame('Dr', $invitation->inviteeTitle);
    }

    public function testForSoignantRelationHasNullTitleWhenNoneIsSet(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = $this->makeSoignantRelation($patient, $soignant, 7);

        $invitation = LiaisonInvitation::forSoignantRelation($relation);

        self::assertNull($invitation->inviteeTitle);
    }

    public function testForAidantRelationHasNoTitle(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = $this->makeAidantRelation($patient, $aidant, 5);

        $invitation = LiaisonInvitation::forAidantRelation($relation);

        self::assertNull($invitation->inviteeTitle);
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

        (new \ReflectionProperty(PatientAidant::class, 'id'))->setValue($relation, $id);

        return $relation;
    }

    private function makeSoignantRelation(User $patient, User $soignant, int $id): PatientSoignant
    {
        $relation = new PatientSoignant($patient, $soignant);

        (new \ReflectionProperty(PatientSoignant::class, 'id'))->setValue($relation, $id);

        return $relation;
    }
}
