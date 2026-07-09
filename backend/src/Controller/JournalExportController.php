<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\EmptyJournalExportException;
use App\Exception\InvalidExportPeriodException;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\JournalExportPdfService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class JournalExportController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserRepository $userRepository,
        private readonly JournalExportPdfService $journalExportPdfService,
    ) {
    }

    #[Route('/api/export/pdf', name: 'api_export_pdf', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        $patient = $this->resolveTargetPatient($currentUser, $request);

        if (!$this->security->isGranted(UserVoter::VIEW, $patient)) {
            throw new AccessDeniedHttpException("Vous n'avez pas accès au journal de ce patient.");
        }

        $from = $this->parseDate($request->query->get('from'), 'from');
        $to = $this->parseDate($request->query->get('to'), 'to');

        try {
            $pdf = $this->journalExportPdfService->generate($patient, $from, $to);
        } catch (InvalidExportPeriodException|EmptyJournalExportException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $filename = sprintf('medlink_suivi_%s.pdf', (new \DateTimeImmutable())->format('Y-m-d'));

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    private function resolveTargetPatient(User $currentUser, Request $request): User
    {
        if (in_array(User::ROLE_PATIENT, $currentUser->getRoles(), true)) {
            return $currentUser;
        }

        $patientId = $request->query->get('patient');
        if (null === $patientId) {
            throw new BadRequestHttpException('Le paramètre "patient" est obligatoire pour ce rôle.');
        }

        $patient = $this->userRepository->find((int) $patientId);
        if (!$patient instanceof User || !in_array(User::ROLE_PATIENT, $patient->getRoles(), true)) {
            throw new NotFoundHttpException('Patient introuvable.');
        }

        return $patient;
    }

    private function parseDate(?string $value, string $field): \DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            throw new BadRequestHttpException(sprintf('Le paramètre "%s" est obligatoire (format AAAA-MM-JJ).', $field));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date) {
            throw new BadRequestHttpException(sprintf('Le paramètre "%s" doit être au format AAAA-MM-JJ.', $field));
        }

        return $date;
    }
}
