<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\PasswordResetMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PasswordResetMailerTest extends TestCase
{
    public function testSendResetLinkSendsAnEmailWithTheTokenInTheLink(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) {
                self::assertSame('no-reply@medlink.app', $email->getFrom()[0]->getAddress());
                self::assertSame('patient@medlink.test', $email->getTo()[0]->getAddress());
                self::assertStringContainsString(
                    'http://localhost:5173/reset-password?token=le-token-en-clair',
                    (string) $email->getTextBody(),
                );

                return true;
            }));

        $passwordResetMailer = new PasswordResetMailer($mailer, 'http://localhost:5173', 'medlink', 'no-reply@medlink.app');

        $passwordResetMailer->sendResetLink($user, 'le-token-en-clair');
    }

    public function testFrontendUrlTrailingSlashIsNotDoubled(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) {
                self::assertStringContainsString(
                    'http://localhost:5173/reset-password?token=abc',
                    (string) $email->getTextBody(),
                );
                self::assertStringNotContainsString('5173//reset-password', (string) $email->getTextBody());

                return true;
            }));

        $passwordResetMailer = new PasswordResetMailer($mailer, 'http://localhost:5173/', 'medlink', 'no-reply@medlink.app');

        $passwordResetMailer->sendResetLink($user, 'abc');
    }

    public function testSendResetLinkUsesTheMobileSchemeForTheMobilePlatform(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) {
                self::assertStringContainsString(
                    'medlink://reset-password?token=le-token-en-clair',
                    (string) $email->getTextBody(),
                );
                self::assertStringNotContainsString('http://localhost:5173', (string) $email->getTextBody());

                return true;
            }));

        $passwordResetMailer = new PasswordResetMailer($mailer, 'http://localhost:5173', 'medlink', 'no-reply@medlink.app');

        $passwordResetMailer->sendResetLink($user, 'le-token-en-clair', PasswordResetMailer::PLATFORM_MOBILE);
    }
}
