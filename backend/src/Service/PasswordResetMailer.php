<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PasswordResetMailer
{
    public const PLATFORM_WEB = 'web';
    public const PLATFORM_MOBILE = 'mobile';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $frontendUrl,
        private readonly string $mobileScheme,
        private readonly string $fromAddress,
    ) {
    }

    public function sendResetLink(User $user, string $rawToken, string $platform = self::PLATFORM_WEB): void
    {
        $link = self::PLATFORM_MOBILE === $platform
            ? sprintf('%s://reset-password?token=%s', $this->mobileScheme, $rawToken)
            : sprintf('%s/reset-password?token=%s', rtrim($this->frontendUrl, '/'), $rawToken);

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('MedLink — Réinitialisation de votre mot de passe')
            ->text(sprintf(
                "Bonjour %s,\n\n".
                "Vous avez demandé la réinitialisation de votre mot de passe MedLink.\n".
                "Cliquez sur le lien suivant (valable 1 heure) pour en choisir un nouveau :\n%s\n\n".
                "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe reste inchangé.",
                $user->getFirstName(),
                $link,
            ));

        $this->mailer->send($email);
    }
}
