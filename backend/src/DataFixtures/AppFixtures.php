<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\JournalEntry;
use App\Entity\Message;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de développement/démonstration.
 *
 * ATTENTION : ne jamais charger ces fixtures en production. Il s'agit de
 * données de santé fictives destinées uniquement au développement local
 * et aux tests manuels (voir ML-42).
 */
class AppFixtures extends Fixture
{
    /**
     * Shared password for every fixture user (dev/test environments only).
     */
    public const TEST_USER_PASSWORD = 'MedLink2026!';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $soignant = $this->createUser($manager, 'soignant@medlink.test', 'Camille', 'Dubois', User::ROLE_SOIGNANT);
        $soignant->setTitle('Dr');
        $patient1 = $this->createUser($manager, 'patient1@medlink.test', 'Alice', 'Martin', User::ROLE_PATIENT);
        $patient2 = $this->createUser($manager, 'patient2@medlink.test', 'Chloe', 'Bernard', User::ROLE_PATIENT);
        $patient3 = $this->createUser($manager, 'patient3@medlink.test', 'David', 'Lefevre', User::ROLE_PATIENT);
        $patient4 = $this->createUser($manager, 'patient4@medlink.test', 'Emma', 'Rousseau', User::ROLE_PATIENT);
        $aidant1 = $this->createUser($manager, 'aidant1@medlink.test', 'Bruno', 'Nguyen', User::ROLE_AIDANT);
        $aidant2 = $this->createUser($manager, 'aidant2@medlink.test', 'Fatou', 'Diallo', User::ROLE_AIDANT);
        // Aucune relation avec un patient : sert à tester le cas nominal
        // d'invitation d'un soignant (ML-44/45), tous les autres patients
        // étant déjà rattachés au seul soignant ci-dessus.
        $this->createUser($manager, 'soignant2@medlink.test', 'Karim', 'Haddad', User::ROLE_SOIGNANT);
        $this->createUser($manager, 'admin@medlink.test', 'Diane', 'Petit', User::ROLE_ADMIN);

        // Relations Patient <-> Aidant
        $manager->persist(new PatientAidant($patient1, $aidant1));
        $manager->persist(new PatientAidant($patient2, $aidant1));
        $manager->persist((new PatientAidant($patient3, $aidant2))->setActive(false));

        // Relations Patient <-> Soignant
        $manager->persist(new PatientSoignant($patient1, $soignant));
        $manager->persist(new PatientSoignant($patient2, $soignant));
        $manager->persist(new PatientSoignant($patient3, $soignant));
        $manager->persist((new PatientSoignant($patient4, $soignant))->setActive(false));

        // Journal de suivi patient1 : couvre les 3 plages de dates + une entrée saisie par l'aidant
        $this->createJournalEntry($manager, $patient1, $patient1, '-2 days', 4, 2, '120/80', 'RAS, journée normale.');
        $this->createJournalEntry($manager, $patient1, $patient1, '-15 days', 3, 4, '125/82');
        $this->createJournalEntry($manager, $patient1, $patient1, '-45 days', 2, 6, '130/85', 'Douleur persistante depuis quelques jours.');
        $this->createJournalEntry($manager, $patient1, $aidant1, '-1 days', 3, 3, '118/76', "Saisie par l'aidant en l'absence du patient.");

        // Journal de suivi patient2 (rattaché au même aidant, pour tester le cas multi-patients)
        $this->createJournalEntry($manager, $patient2, $patient2, '-3 days', 5, 1, '115/75');

        // Journal de suivi patient3 (dont la relation aidant est désactivée)
        $this->createJournalEntry($manager, $patient3, $patient3, '-10 days', 3, 5, '128/84');

        // patient4 : aucune entrée de journal (cas "aucune donnée")

        // Traitements patient1 : couvre les statuts visuels du jour (pris, à
        // prendre) + un traitement arrêté qui ne doit plus apparaître dans la
        // liste du jour. Bisoprolol est pris 3 fois par jour (matin/midi/
        // soir), pour exercer la carte à plusieurs horaires (ML-50).
        $bisoprolol = $this->createTreatment($manager, $patient1, $soignant, 'Bisoprolol', '5 mg', [
            $this->morning(),
            $this->noon(),
            $this->evening(),
        ]);
        $bisoprololSchedules = $bisoprolol->getSchedules();
        $this->createTreatmentIntake($manager, $bisoprololSchedules[0], taken: true, takenAtModifier: 'today 08:05');
        $this->createTreatmentIntake($manager, $bisoprololSchedules[1], taken: false);
        $this->createTreatmentIntake($manager, $bisoprololSchedules[2], taken: false);

        $ramipril1 = $this->createTreatment($manager, $patient1, $soignant, 'Ramipril', '10 mg', [$this->evening()]);
        $this->createTreatmentIntake($manager, $ramipril1->getSchedules()[0], taken: false);

        $furosemide = $this->createTreatment($manager, $patient1, $soignant, 'Furosémide', '20 mg', [$this->morning()]);
        $this->createTreatmentIntake($manager, $furosemide->getSchedules()[0], taken: false);

        $this->createTreatment($manager, $patient1, $soignant, 'Paracétamol', '500 mg', [$this->noon()], active: false);

        // Traitements patient2
        $metformine = $this->createTreatment($manager, $patient2, $soignant, 'Metformine', '850 mg', [$this->morning()]);
        $this->createTreatmentIntake($manager, $metformine->getSchedules()[0], taken: true, takenAtModifier: 'today 08:10');

        $levothyrox = $this->createTreatment($manager, $patient2, $soignant, 'Levothyrox', '75 µg', [$this->morning()]);
        $this->createTreatmentIntake($manager, $levothyrox->getSchedules()[0], taken: false);

        // Traitements patient3 (relation aidant désactivée, relation soignant active)
        $aspirine = $this->createTreatment($manager, $patient3, $soignant, 'Aspirine', '75 mg', [$this->morning()]);
        $this->createTreatmentIntake($manager, $aspirine->getSchedules()[0], taken: true, takenAtModifier: 'today 08:00');

        // Ramipril3 démontre l'horaire "Personnalisé" (libellé libre).
        $ramipril3 = $this->createTreatment($manager, $patient3, $soignant, 'Ramipril', '10 mg', [$this->custom('Avant le coucher')]);
        $this->createTreatmentIntake($manager, $ramipril3->getSchedules()[0], taken: false);

        // patient4 : aucun traitement (cas "aucune donnée")

        // Messagerie patient1 <-> soignant : conversation avec plusieurs
        // messages dans les deux sens, mélange de lus/non lus (ML-25).
        $this->createMessage($manager, $patient1, $soignant, "Bonjour docteur, j'ai une question sur mon traitement.", '-2 days', read: true);
        $this->createMessage($manager, $soignant, $patient1, 'Bonjour, je vous écoute.', '-2 days +1 hour', read: true);
        $this->createMessage($manager, $patient1, $soignant, 'Dois-je prendre le Bisoprolol avant ou après le repas ?', '-1 days', read: true);
        $this->createMessage($manager, $soignant, $patient1, 'Après le repas, de préférence le matin.', '-1 days +30 minutes', read: false);

        // Messagerie aidant1 <-> soignant (ML-70) : aidant1 et le soignant
        // sont tous les deux rattachés activement à patient1, la conversation
        // est donc autorisée bien qu'aucun des deux ne soit patient.
        $this->createMessage($manager, $aidant1, $soignant, "Bonjour, je suis l'aidant d'Alice. Un point sur son suivi ?", '-1 days', read: true);
        $this->createMessage($manager, $soignant, $aidant1, 'Bonjour, tout va bien, sa tension est stable.', '-1 days +15 minutes', read: false);

        // Rendez-vous patient1 <-> soignant (ML-27) : un RDV passé (déjà
        // terminé), un RDV à plus de 24h et un RDV à moins de 24h, ce dernier
        // servant à tester le rappel visuel de ML-28.
        $this->createAppointment($manager, $patient1, $soignant, '-5 days', Appointment::STATUS_COMPLETED, 'Consultation de suivi.');
        $this->createAppointment($manager, $patient1, $soignant, '+3 days', Appointment::STATUS_PLANNED);
        $this->createAppointment($manager, $patient1, $soignant, '+18 hours', Appointment::STATUS_PLANNED, 'Contrôle de la tension.');

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, string $firstName, string $lastName, string $role): User
    {
        $user = new User($email, $firstName, $lastName);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::TEST_USER_PASSWORD));
        $manager->persist($user);

        return $user;
    }

    private function createJournalEntry(
        ObjectManager $manager,
        User $patient,
        User $author,
        string $createdAtModifier,
        int $mood,
        int $painLevel,
        string $bloodPressure,
        ?string $note = null,
    ): void {
        $entry = new JournalEntry($patient, $author, $mood, $painLevel, $bloodPressure, $note);

        // La date de création doit pouvoir être antérieure à "maintenant" pour
        // simuler un historique ; l'entité ne l'expose pas via un setter car ce
        // n'est pas modifiable en dehors des fixtures.
        $createdAt = (new \ReflectionProperty($entry, 'createdAt'));
        $createdAt->setValue($entry, new \DateTimeImmutable($createdAtModifier));

        $manager->persist($entry);
    }

    private function createMessage(
        ObjectManager $manager,
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

        // Comme pour le journal, la date de création doit pouvoir être
        // antérieure à "maintenant" pour simuler un historique de conversation.
        $createdAt = (new \ReflectionProperty($message, 'createdAt'));
        $createdAt->setValue($message, new \DateTimeImmutable($createdAtModifier));

        $manager->persist($message);
    }

    /**
     * @param list<array{moment: string, label?: ?string}> $schedules
     */
    private function createTreatment(
        ObjectManager $manager,
        User $patient,
        User $soignant,
        string $name,
        string $dosage,
        array $schedules,
        bool $active = true,
    ): Treatment {
        $treatment = new Treatment($patient, $name, $dosage, $soignant);
        if (!$active) {
            $treatment->setActive(false);
        }

        $manager->persist($treatment);

        foreach ($schedules as $item) {
            $schedule = new TreatmentSchedule($treatment, $item['moment'], $item['label'] ?? null);
            $treatment->addSchedule($schedule);
            $manager->persist($schedule);
        }

        return $treatment;
    }

    private function createTreatmentIntake(
        ObjectManager $manager,
        TreatmentSchedule $schedule,
        bool $taken,
        ?string $takenAtModifier = null,
    ): void {
        $intake = new TreatmentIntake($schedule, new \DateTimeImmutable('today'));
        if ($taken) {
            $intake->markTaken(new \DateTimeImmutable($takenAtModifier ?? 'now'));
        }

        $manager->persist($intake);
    }

    private function createAppointment(
        ObjectManager $manager,
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

        $manager->persist($appointment);
    }

    /**
     * @return array{moment: string}
     */
    private function morning(): array
    {
        return ['moment' => TreatmentSchedule::MOMENT_MORNING];
    }

    /**
     * @return array{moment: string}
     */
    private function noon(): array
    {
        return ['moment' => TreatmentSchedule::MOMENT_NOON];
    }

    /**
     * @return array{moment: string}
     */
    private function evening(): array
    {
        return ['moment' => TreatmentSchedule::MOMENT_EVENING];
    }

    /**
     * @return array{moment: string, label: string}
     */
    private function custom(string $label): array
    {
        return ['moment' => TreatmentSchedule::MOMENT_CUSTOM, 'label' => $label];
    }
}
