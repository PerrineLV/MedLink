<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistrationInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationService
{
    private const ROLE_MAP = [
        'patient' => User::ROLE_PATIENT,
        'aidant' => User::ROLE_AIDANT,
        'soignant' => User::ROLE_SOIGNANT,
    ];

    private const PASSWORD_MIN_LENGTH = 8;
    private const NAME_MAX_LENGTH = 100;
    private const EMAIL_MAX_LENGTH = 180;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(RegistrationInput $input): User
    {
        $role = $this->resolveRole($input->role);
        $this->assertValid($input);

        if (null !== $this->userRepository->findOneBy(['email' => $input->email])) {
            throw new ConflictHttpException('Cet email est déjà utilisé.');
        }

        $user = new User($input->email, $input->firstName, $input->lastName);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->password));
        $user->setConsentAt(new \DateTimeImmutable());

        // title n'est pertinent que pour un soignant : ignoré silencieusement
        // dans les autres cas (choix retenu par la spec ML-57).
        if (User::ROLE_SOIGNANT === $role && null !== $input->title) {
            $user->setTitle($input->title);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function resolveRole(string $role): string
    {
        if (!array_key_exists($role, self::ROLE_MAP)) {
            throw new BadRequestHttpException('Rôle invalide. Rôles autorisés : patient, aidant, soignant.');
        }

        return self::ROLE_MAP[$role];
    }

    private function assertValid(RegistrationInput $input): void
    {
        if (!filter_var($input->email, \FILTER_VALIDATE_EMAIL) || mb_strlen($input->email) > self::EMAIL_MAX_LENGTH) {
            throw new BadRequestHttpException('Adresse email invalide.');
        }

        if (mb_strlen($input->firstName) > self::NAME_MAX_LENGTH || mb_strlen($input->lastName) > self::NAME_MAX_LENGTH) {
            throw new BadRequestHttpException(sprintf('Le prénom et le nom ne peuvent pas dépasser %d caractères.', self::NAME_MAX_LENGTH));
        }

        if (mb_strlen($input->password) < self::PASSWORD_MIN_LENGTH
            || 1 !== preg_match('/\d/', $input->password)
            || 1 !== preg_match('/[A-Za-z]/', $input->password)
        ) {
            throw new BadRequestHttpException(sprintf('Le mot de passe doit contenir au moins %d caractères, dont un chiffre et une lettre.', self::PASSWORD_MIN_LENGTH));
        }

        if (!$input->consent) {
            throw new BadRequestHttpException('Le consentement au traitement des données de santé est obligatoire.');
        }
    }
}
