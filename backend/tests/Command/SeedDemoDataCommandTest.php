<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SeedDemoDataCommand;
use App\Entity\Appointment;
use App\Entity\JournalEntry;
use App\Entity\JournalEntryComment;
use App\Entity\Message;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoDataCommandTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private UserRepository&Stub $userRepository;
    private UserPasswordHasherInterface&Stub $passwordHasher;

    /**
     * Emails que le dépôt doit considérer comme déjà présents en base.
     *
     * @var list<string>
     */
    private array $existingEmails = [];

    /**
     * Toutes les entités passées à persist(), pour vérifier ce que la commande
     * écrit réellement sans base de données.
     *
     * @var list<object>
     */
    private array $persisted = [];

    /**
     * Mots de passe en clair soumis au hasher, pour vérifier l'option --password.
     *
     * @var list<string>
     */
    private array $hashedPlainPasswords = [];

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->hashedPlainPasswords = [];
        $this->existingEmails = [];

        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback($this->recordPersist());

        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('findOneBy')->willReturnCallback(
            function (array $criteria): ?User {
                $email = $criteria['email'] ?? null;

                return is_string($email) && in_array($email, $this->existingEmails, true)
                    ? new User($email, 'Déjà', 'Là')
                    : null;
            },
        );

        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturnCallback(
            function (object $user, string $plainPassword): string {
                $this->hashedPlainPasswords[] = $plainPassword;

                return 'hashed:'.$plainPassword;
            },
        );
    }

    public function testCreatesTheThreeDemoAccountsWithTheExpectedRoles(): void
    {
        $this->expectFlush(self::once());

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $users = $this->persistedOfType(User::class);
        self::assertCount(3, $users);

        $rolesByEmail = [];
        foreach ($users as $user) {
            $rolesByEmail[$user->getEmail()] = $user->getRoles();
        }

        self::assertSame(
            [
                SeedDemoDataCommand::PATIENT_EMAIL => [User::ROLE_PATIENT],
                SeedDemoDataCommand::AIDANT_EMAIL => [User::ROLE_AIDANT],
                SeedDemoDataCommand::SOIGNANT_EMAIL => [User::ROLE_SOIGNANT],
            ],
            $rolesByEmail,
        );
    }

    public function testDemoAccountsAreUsableImmediately(): void
    {
        $this->execute();

        foreach ($this->persistedOfType(User::class) as $user) {
            self::assertTrue($user->isActive(), 'Un compte de démo doit être actif.');
            self::assertNotNull($user->getConsentAt(), 'Le consentement RGPD doit être enregistré.');
            self::assertSame('hashed:'.SeedDemoDataCommand::DEFAULT_PASSWORD, $user->getPassword());
        }
    }

    /**
     * Le fil de démo doit couvrir chaque fonctionnalité du MVP : sans données
     * pour l'une d'elles, l'écran correspondant est vide pendant la démo.
     *
     * @return iterable<string, array{class-string, int}>
     */
    public static function expectedEntityCountProvider(): iterable
    {
        yield 'liaison patient-aidant' => [PatientAidant::class, 1];
        yield 'entrées de journal' => [JournalEntry::class, 5];
        yield 'commentaire soignant' => [JournalEntryComment::class, 1];
        yield 'rendez-vous' => [Appointment::class, 3];
    }

    #[DataProvider('expectedEntityCountProvider')]
    public function testSeedsTheDataNeededByEachMvpFeature(string $class, int $expectedCount): void
    {
        $this->execute();

        self::assertCount($expectedCount, $this->persistedOfType($class));
    }

    /**
     * La liaison patient↔soignant est volontairement absente : la démo doit
     * pouvoir dérouler le flux d'invitation réel (le patient invite, le
     * soignant accepte), qui est ce qui rend les données ci-dessous visibles
     * côté soignant.
     *
     * Une ligne simplement inactive ne conviendrait pas : la vérification de
     * doublon de LiaisonInvitationService::createSoignantInvitation() ignore
     * le champ "active", donc toute ligne existante ferait échouer
     * l'invitation en 409 pendant la démo.
     */
    public function testLeavesThePatientAndSoignantUnlinkedSoTheInvitationCanBeDemonstrated(): void
    {
        $this->execute();

        self::assertSame([], $this->persistedOfType(PatientSoignant::class));
    }

    /**
     * Les données rattachées au soignant sont bien créées malgré l'absence de
     * liaison : elles restent invisibles pour lui jusqu'à ce que l'invitation
     * soit acceptée, et apparaissent d'un coup à ce moment-là.
     */
    public function testStillSeedsTheSoignantSideDataDespiteTheMissingLink(): void
    {
        $this->execute();

        self::assertNotEmpty($this->persistedOfType(JournalEntryComment::class));
        self::assertNotEmpty($this->persistedOfType(Appointment::class));
        self::assertNotEmpty($this->persistedOfType(Treatment::class));
    }

    public function testSeedsConversationsInBothDirections(): void
    {
        $this->execute();

        $messages = $this->persistedOfType(Message::class);
        self::assertGreaterThanOrEqual(4, count($messages));

        $pairs = [];
        foreach ($messages as $message) {
            $pairs[] = $message->getSender()->getEmail().' -> '.$message->getRecipient()->getEmail();
        }

        self::assertContains(
            SeedDemoDataCommand::PATIENT_EMAIL.' -> '.SeedDemoDataCommand::SOIGNANT_EMAIL,
            $pairs,
        );
        self::assertContains(
            SeedDemoDataCommand::SOIGNANT_EMAIL.' -> '.SeedDemoDataCommand::PATIENT_EMAIL,
            $pairs,
        );
        self::assertContains(
            SeedDemoDataCommand::AIDANT_EMAIL.' -> '.SeedDemoDataCommand::SOIGNANT_EMAIL,
            $pairs,
        );
    }

    public function testSeedsJournalEntriesWrittenByTheAidantOnBehalfOfThePatient(): void
    {
        $this->execute();

        $byAidant = array_filter(
            $this->persistedOfType(JournalEntry::class),
            static fn (JournalEntry $entry): bool => SeedDemoDataCommand::AIDANT_EMAIL === $entry->getAuthor()->getEmail(),
        );

        self::assertNotEmpty($byAidant, "Il faut au moins une entrée saisie par l'aidant.");

        foreach ($byAidant as $entry) {
            self::assertSame(
                SeedDemoDataCommand::PATIENT_EMAIL,
                $entry->getPatient()->getEmail(),
                "Une saisie par l'aidant reste rattachée au patient.",
            );
        }
    }

    public function testSeedsTreatmentsWithTodaysIntakes(): void
    {
        $this->execute();

        self::assertNotEmpty($this->persistedOfType(Treatment::class));
        self::assertNotEmpty($this->persistedOfType(TreatmentSchedule::class));

        $intakes = $this->persistedOfType(TreatmentIntake::class);
        self::assertNotEmpty($intakes);

        $taken = array_filter($intakes, static fn (TreatmentIntake $intake): bool => $intake->isTaken());
        self::assertNotEmpty($taken, 'Il faut au moins une prise déjà validée.');
        self::assertNotCount(count($intakes), $taken, 'Il faut au moins une prise restant à valider.');
    }

    public function testJournalEntriesSpreadOverTimeToExerciseTheFilters(): void
    {
        $this->execute();

        $now = new \DateTimeImmutable();
        $ages = array_map(
            static fn (JournalEntry $entry): int => (int) $entry->getCreatedAt()->diff($now)->days,
            $this->persistedOfType(JournalEntry::class),
        );

        self::assertNotEmpty(array_filter($ages, static fn (int $days): bool => $days <= 7), 'Filtre "cette semaine".');
        self::assertNotEmpty(array_filter($ages, static fn (int $days): bool => $days > 7 && $days <= 30), 'Filtre "ce mois".');
        self::assertNotEmpty(array_filter($ages, static fn (int $days): bool => $days > 30), 'Filtre "tout".');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function demoEmailProvider(): iterable
    {
        yield 'patient' => [SeedDemoDataCommand::PATIENT_EMAIL];
        yield 'aidant' => [SeedDemoDataCommand::AIDANT_EMAIL];
        yield 'soignant' => [SeedDemoDataCommand::SOIGNANT_EMAIL];
    }

    #[DataProvider('demoEmailProvider')]
    public function testAbortsWithoutWritingAnythingWhenAnAccountAlreadyExists(string $existingEmail): void
    {
        $this->existingEmails = [$existingEmail];
        $this->expectFlush(self::never());

        $tester = $this->execute();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([], $this->persisted, 'Aucune écriture ne doit avoir lieu.');
        self::assertStringContainsString($existingEmail, $tester->getDisplay());
    }

    public function testUsesThePasswordOptionWhenProvided(): void
    {
        $this->execute(['--password' => 'Demo-Jury-2026!']);

        self::assertSame(
            ['Demo-Jury-2026!', 'Demo-Jury-2026!', 'Demo-Jury-2026!'],
            $this->hashedPlainPasswords,
        );
    }

    public function testRejectsAPasswordTooWeakForTheApplicationRules(): void
    {
        $this->expectFlush(self::never());

        $tester = $this->execute(['--password' => 'court']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame([], $this->persisted);
    }

    /**
     * Remplace le gestionnaire d'entités par un mock portant une attente sur
     * flush(). Les autres tests se contentent d'un stub : ils vérifient ce qui
     * est persisté, pas la façon dont la commande appelle Doctrine.
     */
    private function expectFlush(InvocationOrder $invocationOrder): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback($this->recordPersist());
        $entityManager->expects($invocationOrder)->method('flush');

        $this->entityManager = $entityManager;
    }

    /**
     * @return callable(object): void
     */
    private function recordPersist(): callable
    {
        return function (object $entity): void {
            $this->persisted[] = $entity;
        };
    }

    /**
     * @param array<string, string> $input
     */
    private function execute(array $input = []): CommandTester
    {
        $command = new SeedDemoDataCommand(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
        );

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function persistedOfType(string $class): array
    {
        return array_values(array_filter(
            $this->persisted,
            static fn (object $entity): bool => $entity instanceof $class,
        ));
    }
}
