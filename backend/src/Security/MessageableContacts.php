<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Repository\UserRepository;

/**
 * Résout qui un utilisateur donné a le droit de contacter par messagerie
 * (ML-70) :
 *  - un patient ne peut contacter que ses soignants actifs (l'aidant est
 *    exclu : ils communiquent déjà en direct, hors MedLink) ;
 *  - un aidant ne peut contacter que le(s) soignant(s) actif(s) du ou des
 *    patients auxquels il est lui-même activement rattaché (relation
 *    vérifiée via un patient commun, jamais un rattachement "au sens
 *    large") ;
 *  - un soignant peut contacter ses patients actifs, ainsi que les aidants
 *    actifs de ces mêmes patients.
 *
 * Pour une paire aidant/soignant, chaque contact porte aussi la liste des
 * patients communs qui justifient l'autorisation (viaPatients) : utile à
 * afficher quand l'un des deux a plusieurs patients et donc plusieurs
 * contacts de ce type, sans savoir lequel correspond à quel patient.
 *
 * Utilisé à la fois pour lister les contacts (MessageContactCollectionProvider)
 * et pour autoriser l'envoi (MessageVoter) : une seule source de vérité pour
 * éviter que les deux dérivent l'un de l'autre.
 *
 * Pas de "final" : contrairement aux autres services de ce projet, celle-ci
 * est injectée comme collaborateur dans MessageVoter, qui a besoin de la
 * doubler dans ses tests (même raison que PatientAidantRepository /
 * PatientSoignantRepository, non "final" pour la même contrainte PHPUnit).
 */
class MessageableContacts
{
    public function __construct(
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return list<MessageableContact>
     */
    public function forUser(User $user): array
    {
        if (in_array(User::ROLE_PATIENT, $user->getRoles(), true)) {
            $relations = $this->patientSoignantRepository->findActiveForPatients([(int) $user->getId()]);

            // Le patient contacte son propre soignant : pas d'ambiguïté sur
            // "via quel patient", donc pas de viaPatients à porter ici.
            $soignantsById = [];
            foreach ($relations as $relation) {
                $soignant = $relation->getSoignant();
                $soignantsById[$soignant->getId()] ??= new MessageableContact($soignant, User::ROLE_SOIGNANT);
            }

            return array_values($soignantsById);
        }

        if (in_array(User::ROLE_AIDANT, $user->getRoles(), true)) {
            $patientIds = $this->patientAidantRepository->findActivePatientIdsForAidant($user);
            $relations = $this->patientSoignantRepository->findActiveForPatients($patientIds);

            return $this->groupByContact(
                $relations,
                static fn (PatientSoignant $relation): User => $relation->getSoignant(),
                User::ROLE_SOIGNANT,
            );
        }

        if (in_array(User::ROLE_SOIGNANT, $user->getRoles(), true)) {
            $patientIds = $this->patientSoignantRepository->findActivePatientIdsForSoignant($user);
            $patients = [] === $patientIds ? [] : $this->userRepository->findBy(['id' => $patientIds]);
            $patientContacts = array_map(
                static fn (User $patient): MessageableContact => new MessageableContact($patient, User::ROLE_PATIENT),
                $patients,
            );

            $aidantRelations = $this->patientAidantRepository->findActiveForPatients($patientIds);
            $aidantContacts = $this->groupByContact(
                $aidantRelations,
                static fn (PatientAidant $relation): User => $relation->getAidant(),
                User::ROLE_AIDANT,
            );

            return [...$patientContacts, ...$aidantContacts];
        }

        return [];
    }

    /**
     * Regroupe des relations patient<->{aidant|soignant} par contact
     * (dédupliqué), en conservant pour chacun la liste des patients communs
     * qui justifient l'autorisation.
     *
     * @template T of PatientAidant|PatientSoignant
     *
     * @param list<T>           $relations
     * @param callable(T): User $extractContact
     *
     * @return list<MessageableContact>
     */
    private function groupByContact(array $relations, callable $extractContact, string $role): array
    {
        /** @var array<int, array{contact: User, patients: array<int, User>}> $entriesByContactId */
        $entriesByContactId = [];

        foreach ($relations as $relation) {
            $contact = $extractContact($relation);
            $patient = $relation->getPatient();

            $entriesByContactId[$contact->getId()]['contact'] ??= $contact;
            $entriesByContactId[$contact->getId()]['patients'][$patient->getId()] = $patient;
        }

        return array_values(array_map(
            static fn (array $entry): MessageableContact => new MessageableContact(
                $entry['contact'],
                $role,
                array_values($entry['patients']),
            ),
            $entriesByContactId,
        ));
    }
}
