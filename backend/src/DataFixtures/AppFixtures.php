<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\JournalEntry;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
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
        $patient1 = $this->createUser($manager, 'patient1@medlink.test', 'Alice', 'Martin', User::ROLE_PATIENT);
        $patient2 = $this->createUser($manager, 'patient2@medlink.test', 'Chloe', 'Bernard', User::ROLE_PATIENT);
        $patient3 = $this->createUser($manager, 'patient3@medlink.test', 'David', 'Lefevre', User::ROLE_PATIENT);
        $patient4 = $this->createUser($manager, 'patient4@medlink.test', 'Emma', 'Rousseau', User::ROLE_PATIENT);
        $aidant1 = $this->createUser($manager, 'aidant1@medlink.test', 'Bruno', 'Nguyen', User::ROLE_AIDANT);
        $aidant2 = $this->createUser($manager, 'aidant2@medlink.test', 'Fatou', 'Diallo', User::ROLE_AIDANT);
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

        // Traitements patient1 : couvre les 3 statuts visuels du jour (pris,
        // à prendre, en retard) + un traitement arrêté qui ne doit plus
        // apparaître dans la liste du jour.
        $bisoprolol = $this->createTreatment($manager, $patient1, $soignant, 'Bisoprolol', '5 mg', '08:00');
        $this->createTreatmentIntake($manager, $bisoprolol, taken: true, takenAtModifier: 'today 08:05');

        $ramipril1 = $this->createTreatment($manager, $patient1, $soignant, 'Ramipril', '10 mg', $this->hoursFromNow(3));
        $this->createTreatmentIntake($manager, $ramipril1, taken: false);

        $furosemide = $this->createTreatment($manager, $patient1, $soignant, 'Furosémide', '20 mg', $this->hoursFromNow(-3));
        $this->createTreatmentIntake($manager, $furosemide, taken: false);

        $this->createTreatment($manager, $patient1, $soignant, 'Paracétamol', '500 mg', '12:00', active: false);

        // Traitements patient2
        $metformine = $this->createTreatment($manager, $patient2, $soignant, 'Metformine', '850 mg', '08:00');
        $this->createTreatmentIntake($manager, $metformine, taken: true, takenAtModifier: 'today 08:10');

        $levothyrox = $this->createTreatment($manager, $patient2, $soignant, 'Levothyrox', '75 µg', $this->hoursFromNow(-2));
        $this->createTreatmentIntake($manager, $levothyrox, taken: false);

        // Traitements patient3 (relation aidant désactivée, relation soignant active)
        $aspirine = $this->createTreatment($manager, $patient3, $soignant, 'Aspirine', '75 mg', '08:00');
        $this->createTreatmentIntake($manager, $aspirine, taken: true, takenAtModifier: 'today 08:00');

        $ramipril3 = $this->createTreatment($manager, $patient3, $soignant, 'Ramipril', '10 mg', $this->hoursFromNow(4));
        $this->createTreatmentIntake($manager, $ramipril3, taken: false);

        // patient4 : aucun traitement (cas "aucune donnée")

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

    private function createTreatment(
        ObjectManager $manager,
        User $patient,
        User $soignant,
        string $name,
        string $dosage,
        string $scheduledTime,
        bool $active = true,
    ): Treatment {
        $treatment = new Treatment($patient, $name, $dosage, $scheduledTime, $soignant);
        if (!$active) {
            $treatment->setActive(false);
        }

        $manager->persist($treatment);

        return $treatment;
    }

    private function createTreatmentIntake(
        ObjectManager $manager,
        Treatment $treatment,
        bool $taken,
        ?string $takenAtModifier = null,
    ): void {
        $intake = new TreatmentIntake($treatment, new \DateTimeImmutable('today'));
        if ($taken) {
            $intake->markTaken(new \DateTimeImmutable($takenAtModifier ?? 'now'));
        }

        $manager->persist($intake);
    }

    /**
     * Heure du jour décalée de $hours par rapport à maintenant, au format
     * "HH:MM" — permet aux fixtures de rester "en retard"/"à prendre" de
     * façon stable quelle que soit l'heure de chargement.
     */
    private function hoursFromNow(int $hours): string
    {
        return (new \DateTimeImmutable(sprintf('%+d hours', $hours)))->format('H:i');
    }
}
