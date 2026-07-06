<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        foreach ($this->getTestUsers() as [$email, $firstName, $lastName, $role]) {
            $user = new User($email, $firstName, $lastName);
            $user->setRoles([$role]);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::TEST_USER_PASSWORD));

            $manager->persist($user);
        }

        $manager->flush();
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    private function getTestUsers(): array
    {
        return [
            ['patient@medlink.test', 'Alice', 'Martin', User::ROLE_PATIENT],
            ['aidant@medlink.test', 'Bruno', 'Nguyen', User::ROLE_AIDANT],
            ['soignant@medlink.test', 'Camille', 'Dubois', User::ROLE_SOIGNANT],
            ['admin@medlink.test', 'Diane', 'Petit', User::ROLE_ADMIN],
        ];
    }
}
