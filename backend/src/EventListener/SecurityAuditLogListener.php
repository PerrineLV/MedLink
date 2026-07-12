<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Journalise les événements de sécurité dans le canal "security_audit"
 * (ML-31) : tentatives de login échouées, 403, 5xx. Ne logge jamais l'email,
 * l'IP ou le message de l'exception : seuls la route et la méthode HTTP sont
 * conservés (A09, RGPD — pas de donnée personnelle dans les logs).
 *
 * Volontairement pas "monolog.logger.security" : c'est le canal interne du
 * composant Security de Symfony, qui logge lui-même l'email en cas
 * d'UserNotFoundException — voir config/packages/monolog.yaml.
 */
final class SecurityAuditLogListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.security_audit')]
        private readonly LoggerInterface $securityLogger,
    ) {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $this->securityLogger->warning('Tentative de connexion échouée');
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $context = [
            'route' => $event->getRequest()->attributes->get('_route'),
            'method' => $event->getRequest()->getMethod(),
        ];

        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            $this->securityLogger->warning('Accès refusé (403)', $context);

            return;
        }

        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        if ($statusCode >= 500) {
            $context['exception_class'] = $exception::class;
            $this->securityLogger->error('Erreur serveur interne', $context);
        }
    }
}
