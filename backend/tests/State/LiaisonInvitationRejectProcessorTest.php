<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\PatientAidant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Service\LiaisonInvitationService;
use App\State\LiaisonInvitationRejectProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LiaisonInvitationRejectProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LiaisonInvitationRejectProcessor $processor;

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

        $this->processor = new LiaisonInvitationRejectProcessor($liaisonInvitationService);
    }

    public function testRejectsTheRelationFromReadData(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $aidant = new User('aidant@medlink.test', 'Paul', 'Martin');
        $relation = new PatientAidant($patient, $aidant);
        $relation->setActive(false);

        $this->entityManager->expects(self::once())->method('remove')->with($relation);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process(null, new Patch(), [], ['read_data' => $relation]);

        self::assertSame((int) $aidant->getId(), $result->inviteeId);
    }

    public function testThrowsNotFoundWhenReadDataIsNotALiaisonRelation(): void
    {
        $this->entityManager->expects(self::never())->method('remove');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Patch(), [], ['read_data' => null]);
    }
}
