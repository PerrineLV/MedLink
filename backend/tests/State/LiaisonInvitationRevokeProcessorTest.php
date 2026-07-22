<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\PatientAidant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Service\LiaisonInvitationService;
use App\State\LiaisonInvitationRevokeProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LiaisonInvitationRevokeProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LiaisonInvitationRevokeProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $security = $this->createStub(Security::class);

        $liaisonInvitationService = new LiaisonInvitationService(
            $this->entityManager,
            $patientAidantRepository,
            $patientSoignantRepository,
            $security,
        );

        $this->processor = new LiaisonInvitationRevokeProcessor($liaisonInvitationService);
    }

    public function testRevokesTheRelationFromReadData(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $aidant = new User('aidant@medlink.test', 'Paul', 'Martin');
        $relation = new PatientAidant($patient, $aidant);
        $relation->setActive(true);

        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process(null, new Patch(), [], ['read_data' => $relation]);

        self::assertFalse($relation->isActive());
        self::assertSame((int) $aidant->getId(), $result->inviteeId);
    }

    public function testThrowsNotFoundWhenReadDataIsNotALiaisonRelation(): void
    {
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Patch(), [], ['read_data' => null]);
    }
}
