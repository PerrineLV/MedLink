<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetService
{
    private const TOKEN_TTL = 'PT1H';
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PasswordResetTokenRepository $passwordResetTokenRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordResetMailer $passwordResetMailer,
    ) {
    }

    /**
     * Always returns without error, whether or not the email matches an
     * account: the caller (controller) replies with the same generic
     * message either way, so a bad actor can't use this endpoint to test
     * which emails are registered (REC-01c anti-enumeration, cf. ML-78).
     */
    public function requestReset(string $email, string $platform = PasswordResetMailer::PLATFORM_WEB): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User || !$user->isActive()) {
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->add(new \DateInterval(self::TOKEN_TTL));

        $resetToken = new PasswordResetToken($user, self::hashToken($rawToken), $expiresAt);
        $this->entityManager->persist($resetToken);
        $this->entityManager->flush();

        $this->passwordResetMailer->sendResetLink($user, $rawToken, $platform);
    }

    public function confirmReset(string $rawToken, string $newPassword): void
    {
        $resetToken = $this->passwordResetTokenRepository->findOneByTokenHash(self::hashToken($rawToken));
        if (!$resetToken instanceof PasswordResetToken) {
            throw new BadRequestHttpException('Lien de réinitialisation invalide.');
        }

        if ($resetToken->isUsed()) {
            throw new GoneHttpException('Ce lien de réinitialisation a déjà été utilisé. Merci de refaire une demande.');
        }

        $now = new \DateTimeImmutable();
        if ($resetToken->isExpired($now)) {
            throw new GoneHttpException('Ce lien de réinitialisation a expiré. Merci de refaire une demande.');
        }

        $this->assertPasswordIsRobust($newPassword);

        $user = $resetToken->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $resetToken->markUsed($now);

        $this->revokeRefreshTokens($user->getEmail());

        $this->entityManager->flush();
    }

    private static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Cf. App\Service\AccountService::revokeRefreshTokens — a reset must
     * invalidate any session issued under the old password, same as a
     * voluntary logout or an account password/email change.
     */
    private function revokeRefreshTokens(string $username): void
    {
        $refreshTokenRepository = $this->entityManager->getRepository(RefreshToken::class);
        foreach ($refreshTokenRepository->findBy(['username' => $username]) as $refreshToken) {
            $this->entityManager->remove($refreshToken);
        }
    }

    private function assertPasswordIsRobust(string $password): void
    {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH
            || 1 !== preg_match('/\d/', $password)
            || 1 !== preg_match('/[A-Za-z]/', $password)
        ) {
            throw new BadRequestHttpException(sprintf('Le mot de passe doit contenir au moins %d caractères, dont un chiffre et une lettre.', self::PASSWORD_MIN_LENGTH));
        }
    }
}
