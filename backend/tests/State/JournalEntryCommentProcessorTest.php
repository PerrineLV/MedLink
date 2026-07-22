<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\Dto\JournalEntryCommentInput;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\PatientSoignantRepository;
use App\Service\JournalEntryCommentService;
use App\State\JournalEntryCommentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class JournalEntryCommentProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private JournalEntryRepository&MockObject $journalEntryRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private JournalEntryCommentProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->journalEntryRepository = $this->createMock(JournalEntryRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);

        $journalEntryCommentService = new JournalEntryCommentService(
            $this->entityManager,
            $this->patientSoignantRepository,
            $this->security,
        );

        $this->processor = new JournalEntryCommentProcessor($journalEntryCommentService, $this->journalEntryRepository);
    }

    public function testCreatesACommentOnTheResolvedJournalEntry(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $soignant = new User('soignant@medlink.test', 'Paul', 'Martin');
        $soignant->setRoles([User::ROLE_SOIGNANT]);
        $journalEntry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->journalEntryRepository->expects(self::once())->method('find')->with(1)->willReturn($journalEntry);
        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process(new JournalEntryCommentInput(journalEntryId: 1, text: 'Tout va bien.'), new Post());

        self::assertSame($journalEntry, $result->getJournalEntry());
        self::assertSame($soignant, $result->getAuthor());
        self::assertSame('Tout va bien.', $result->getText());
    }

    public function testThrowsNotFoundWhenJournalEntryDoesNotExist(): void
    {
        $this->journalEntryRepository->expects(self::once())->method('find')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(new JournalEntryCommentInput(journalEntryId: 999, text: 'Tout va bien.'), new Post());
    }
}
