<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Appointment;
use App\Entity\JournalEntry;
use App\Entity\JournalEntryComment;
use App\Entity\Message;
use App\Entity\PatientAidant;
use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée les trois comptes de démonstration (patient, aidant, soignant) et le
 * jeu de données minimal permettant de dérouler les fonctionnalités du MVP.
 *
 * Contrairement à AppFixtures, cette commande est utilisable en production :
 * elle n'efface jamais rien et refuse de s'exécuter si l'un des comptes de
 * démonstration existe déjà. Les données de santé créées sont entièrement
 * fictives et rattachées aux seuls comptes ci-dessous.
 */
#[AsCommand(
    name: self::NAME,
    description: 'Crée les comptes et les données de démonstration MedLink.',
)]
final class SeedDemoDataCommand extends Command
{
    public const NAME = 'app:demo:seed';

    public const PATIENT_EMAIL = 'patient-test@medlink-app.fr';
    public const AIDANT_EMAIL = 'aidant-test@medlink-app.fr';
    public const SOIGNANT_EMAIL = 'soignant-test@medlink-app.fr';

    public const DEFAULT_PASSWORD = 'Test2026!';

    /**
     * Alignée sur RegistrationService : un compte créé ici doit respecter les
     * mêmes règles qu'un compte créé via /api/auth/register.
     */
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe commun aux trois comptes de démonstration.',
                self::DEFAULT_PASSWORD,
            )
            ->setHelp(<<<'HELP'
                Crée trois comptes de démonstration (patient, aidant, soignant) partageant
                le même mot de passe, ainsi que les données fictives associées : journal de
                suivi, commentaire soignant, messagerie, traitements et rendez-vous.

                La commande est sans effet si l'un des comptes existe déjà : elle s'arrête
                alors sans rien écrire. Elle ne supprime jamais de données.
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $password */
        $password = $input->getOption('password');

        if (null !== $error = $this->validatePassword($password)) {
            $io->error($error);

            return Command::INVALID;
        }

        $existing = $this->findExistingDemoEmails();
        if ([] !== $existing) {
            $io->error(sprintf(
                'Ces comptes existent déjà : %s. Aucune donnée n\'a été créée ni modifiée.',
                implode(', ', $existing),
            ));
            $io->note('Supprimez-les manuellement si vous souhaitez repartir d\'un jeu de démonstration vierge.');

            return Command::FAILURE;
        }

        $patient = $this->createUser(self::PATIENT_EMAIL, 'Alice', 'Martin', User::ROLE_PATIENT, $password);
        $aidant = $this->createUser(self::AIDANT_EMAIL, 'Bruno', 'Nguyen', User::ROLE_AIDANT, $password);
        $soignant = $this->createUser(self::SOIGNANT_EMAIL, 'Camille', 'Dubois', User::ROLE_SOIGNANT, $password);
        $soignant->setTitle('Dr');

        $this->entityManager->persist(new PatientAidant($patient, $aidant));

        // Pas de liaison patient↔soignant : la démonstration doit pouvoir
        // dérouler le flux d'invitation réel (le patient invite le soignant,
        // qui accepte), ce qui rend d'un coup visibles côté soignant toutes
        // les données créées plus bas. Une ligne simplement inactive ne
        // conviendrait pas : la vérification de doublon de
        // LiaisonInvitationService::createSoignantInvitation() ignore le champ
        // "active", donc toute ligne existante ferait échouer l'invitation en
        // 409. La liaison patient↔aidant, elle, reste active : le journal côté
        // patient et aidant doit fonctionner sans étape préalable.

        $this->seedJournal($patient, $aidant, $soignant);
        $this->seedMessages($patient, $aidant, $soignant);
        $this->seedTreatments($patient, $soignant);
        $this->seedAppointments($patient, $soignant);

        $this->entityManager->flush();

        $io->success('Comptes et données de démonstration créés.');
        $io->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['Patient', self::PATIENT_EMAIL, $password],
                ['Aidant', self::AIDANT_EMAIL, $password],
                ['Soignant', self::SOIGNANT_EMAIL, $password],
            ],
        );

        return Command::SUCCESS;
    }

    private function validatePassword(string $password): ?string
    {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH
            || 1 !== preg_match('/\d/', $password)
            || 1 !== preg_match('/[A-Za-z]/', $password)
        ) {
            return sprintf(
                'Le mot de passe doit contenir au moins %d caractères, dont un chiffre et une lettre.',
                self::PASSWORD_MIN_LENGTH,
            );
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function findExistingDemoEmails(): array
    {
        $existing = [];

        foreach ([self::PATIENT_EMAIL, self::AIDANT_EMAIL, self::SOIGNANT_EMAIL] as $email) {
            if (null !== $this->userRepository->findOneBy(['email' => $email])) {
                $existing[] = $email;
            }
        }

        return $existing;
    }

    private function createUser(string $email, string $firstName, string $lastName, string $role, string $password): User
    {
        $user = new User($email, $firstName, $lastName);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        // Comme à l'inscription : sans consentement enregistré, le compte ne
        // reflèterait pas un parcours utilisateur réel.
        $user->setConsentAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * Entrées réparties sur plus de 45 jours, pour que les filtres
     * « Cette semaine » / « Ce mois » / « Tout » du journal soignant aient
     * tous quelque chose à afficher, et couvrant les trois plages de badges.
     */
    private function seedJournal(User $patient, User $aidant, User $soignant): void
    {
        $this->createJournalEntry($patient, $patient, '-2 days', 4, 2, '120/80', 'RAS, journée normale.');
        $this->createJournalEntry($patient, $aidant, '-4 days', 3, 3, '118/76', "Saisie par l'aidant, le patient étant fatigué.");
        $this->createJournalEntry($patient, $patient, '-15 days', 3, 4, '125/82');
        $commented = $this->createJournalEntry($patient, $patient, '-20 days', 2, 7, '142/91', 'Douleur vive dans la matinée.');
        $this->createJournalEntry($patient, $aidant, '-45 days', 2, 6, '130/85', "Saisie par l'aidant : douleur persistante depuis quelques jours.");

        $this->entityManager->persist(new JournalEntryComment(
            $commented,
            $soignant,
            'Merci pour ce relevé. Si la douleur dépasse 6, contactez-moi sans attendre le prochain rendez-vous.',
        ));
    }

    private function seedMessages(User $patient, User $aidant, User $soignant): void
    {
        $this->createMessage($patient, $soignant, "Bonjour docteur, j'ai une question sur mon traitement.", '-2 days', read: true);
        $this->createMessage($soignant, $patient, 'Bonjour, je vous écoute.', '-2 days +1 hour', read: true);
        $this->createMessage($patient, $soignant, 'Dois-je prendre le Bisoprolol avant ou après le repas ?', '-1 days', read: true);
        $this->createMessage($soignant, $patient, 'Après le repas, de préférence le matin.', '-1 days +30 minutes', read: false);

        // L'aidant et le soignant sont tous deux rattachés au patient : la
        // conversation entre eux est autorisée (ML-70).
        $this->createMessage($aidant, $soignant, "Bonjour, je suis l'aidant d'Alice. Un point sur son suivi ?", '-1 days', read: true);
        $this->createMessage($soignant, $aidant, 'Bonjour, tout va bien, sa tension est stable.', '-1 days +15 minutes', read: false);
    }

    /**
     * Couvre les statuts visuels du jour (pris / à prendre), un traitement à
     * plusieurs horaires et un traitement arrêté qui ne doit plus apparaître.
     */
    private function seedTreatments(User $patient, User $soignant): void
    {
        $bisoprolol = $this->createTreatment($patient, $soignant, 'Bisoprolol', '5 mg', [
            TreatmentSchedule::MOMENT_MORNING,
            TreatmentSchedule::MOMENT_NOON,
            TreatmentSchedule::MOMENT_EVENING,
        ]);
        $schedules = $bisoprolol->getSchedules();
        $this->createTreatmentIntake($schedules[0], taken: true, takenAtModifier: 'today 08:05');
        $this->createTreatmentIntake($schedules[1], taken: false);
        $this->createTreatmentIntake($schedules[2], taken: false);

        $ramipril = $this->createTreatment($patient, $soignant, 'Ramipril', '10 mg', [TreatmentSchedule::MOMENT_EVENING]);
        $this->createTreatmentIntake($ramipril->getSchedules()[0], taken: false);

        $furosemide = $this->createTreatment($patient, $soignant, 'Furosémide', '20 mg', [TreatmentSchedule::MOMENT_MORNING]);
        $this->createTreatmentIntake($furosemide->getSchedules()[0], taken: true, takenAtModifier: 'today 08:10');

        $this->createTreatment($patient, $soignant, 'Paracétamol', '500 mg', [TreatmentSchedule::MOMENT_NOON], active: false);
    }

    /**
     * Un rendez-vous passé, un à venir, et un à moins de 24 h pour exercer le
     * rappel visuel (ML-28).
     */
    private function seedAppointments(User $patient, User $soignant): void
    {
        $this->createAppointment($patient, $soignant, '-5 days', Appointment::STATUS_COMPLETED, 'Consultation de suivi.');
        $this->createAppointment($patient, $soignant, '+3 days', Appointment::STATUS_PLANNED);
        $this->createAppointment($patient, $soignant, '+18 hours', Appointment::STATUS_PLANNED, 'Contrôle de la tension.');
    }

    private function createJournalEntry(
        User $patient,
        User $author,
        string $createdAtModifier,
        int $mood,
        int $painLevel,
        string $bloodPressure,
        ?string $note = null,
    ): JournalEntry {
        $entry = new JournalEntry($patient, $author, $mood, $painLevel, $bloodPressure, $note);
        $this->backdate($entry, $createdAtModifier);
        $this->entityManager->persist($entry);

        return $entry;
    }

    private function createMessage(
        User $sender,
        User $recipient,
        string $content,
        string $createdAtModifier,
        bool $read,
    ): void {
        $message = new Message($sender, $recipient, $content);
        if ($read) {
            $message->markRead();
        }

        $this->backdate($message, $createdAtModifier);
        $this->entityManager->persist($message);
    }

    /**
     * @param list<string> $moments
     */
    private function createTreatment(
        User $patient,
        User $soignant,
        string $name,
        string $dosage,
        array $moments,
        bool $active = true,
    ): Treatment {
        $treatment = new Treatment($patient, $name, $dosage, $soignant);
        if (!$active) {
            $treatment->setActive(false);
        }

        $this->entityManager->persist($treatment);

        foreach ($moments as $moment) {
            $schedule = new TreatmentSchedule($treatment, $moment);
            $treatment->addSchedule($schedule);
            $this->entityManager->persist($schedule);
        }

        return $treatment;
    }

    private function createTreatmentIntake(
        TreatmentSchedule $schedule,
        bool $taken,
        ?string $takenAtModifier = null,
    ): void {
        $intake = new TreatmentIntake($schedule, new \DateTimeImmutable('today'));
        if ($taken) {
            $intake->markTaken(new \DateTimeImmutable($takenAtModifier ?? 'now'));
        }

        $this->entityManager->persist($intake);
    }

    private function createAppointment(
        User $patient,
        User $soignant,
        string $scheduledAtModifier,
        string $status,
        ?string $notes = null,
    ): void {
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable($scheduledAtModifier), $notes);
        if (Appointment::STATUS_PLANNED !== $status) {
            $appointment->setStatus($status);
        }

        $this->entityManager->persist($appointment);
    }

    /**
     * Un jeu de démonstration crédible a besoin d'un historique : la date de
     * création doit donc pouvoir être antérieure à « maintenant ». Les entités
     * n'exposent volontairement pas de setter pour cette propriété, car elle
     * n'est modifiable dans aucun parcours utilisateur.
     */
    private function backdate(object $entity, string $modifier): void
    {
        (new \ReflectionProperty($entity, 'createdAt'))
            ->setValue($entity, new \DateTimeImmutable($modifier));
    }
}
