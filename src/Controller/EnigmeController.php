<?php

namespace App\Controller;

use App\Entity\Character;
use App\Entity\Enigme;
use App\Entity\Raid;
use App\Entity\RaidStatus;
use App\Exception\BusinessRuleException;
use App\Repository\RaidParticipantRepository;
use App\Service\EnigmeService;
use App\Traits\CsrfGuardTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EnigmeController extends AbstractController
{
    use CsrfGuardTrait;

    private const MAX_COMMENT_LENGTH = 5000;

    public function __construct(
        private readonly RaidParticipantRepository $participantRepo,
        private readonly EnigmeService $enigmeService,
    ) {}

    #[Route('/enigmes/{id}', name: 'app_enigme_show', methods: ['GET'])]
    public function show(Enigme $enigme): Response
    {
        $canComment = $this->getUser() !== null && $this->getParticipantCharacter($enigme->getRaid()) !== null;

        return $this->render('enigme/show.html.twig', [
            'enigme'     => $enigme,
            'canComment' => $canComment,
            'raidClosed' => $enigme->getRaid()->getStatus() === RaidStatus::Closed,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/enigmes/{id}/images', name: 'app_enigme_upload_image', methods: ['POST'])]
    public function uploadImage(Enigme $enigme, Request $request): JsonResponse
    {
        $character = $this->getParticipantCharacter($enigme->getRaid());
        if ($character === null) {
            throw $this->createAccessDeniedException('Vous ne participez pas à ce raid.');
        }
        $this->ensureRaidOpen($enigme);

        if (!$this->isCsrfTokenValid('enigme_' . $enigme->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $file = $request->files->get('image');

        if (!$file) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun fichier reçu — vérifiez upload_max_filesize dans php.ini (actuel : ' . ini_get('upload_max_filesize') . ')'], 400);
        }
        if (!$file->isValid()) {
            $msg = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite PHP : ' . ini_get('upload_max_filesize') . ')',
                UPLOAD_ERR_PARTIAL => 'Upload interrompu',
                default            => 'Erreur upload PHP #' . $file->getError(),
            };
            return new JsonResponse(['success' => false, 'error' => $msg], 400);
        }

        $mime = $file->getMimeType() ?? $file->getClientMimeType();
        if (!str_starts_with($mime, 'image/')) {
            return new JsonResponse(['success' => false, 'error' => 'Type non supporté : ' . $mime], 400);
        }

        try {
            $this->enigmeService->uploadImage($enigme, $file, $character);
        } catch (BusinessRuleException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'success'    => true,
            'updatedAt'  => $enigme->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'imagesHtml' => $this->renderView('enigme/_images.html.twig', ['enigme' => $enigme]),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/enigmes/{id}/comments', name: 'app_enigme_add_comment', methods: ['POST'])]
    public function addComment(Enigme $enigme, Request $request): JsonResponse
    {
        $author = $this->getParticipantCharacter($enigme->getRaid());
        if ($author === null) {
            return new JsonResponse(['success' => false, 'error' => 'Seuls les participants acceptés peuvent commenter.']);
        }
        $this->ensureRaidOpen($enigme);

        if (!$this->isCsrfTokenValid('comment_enigme_' . $enigme->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $content = trim($request->request->get('content', ''));
        if ($content === '') {
            return new JsonResponse(['success' => false, 'error' => 'Commentaire vide'], 400);
        }
        if (mb_strlen($content) > self::MAX_COMMENT_LENGTH) {
            return new JsonResponse(['success' => false, 'error' => 'Commentaire trop long (' . self::MAX_COMMENT_LENGTH . ' caractères maximum)'], 400);
        }

        $this->enigmeService->addComment($enigme, $content, $author);

        return new JsonResponse([
            'success'      => true,
            'updatedAt'    => $enigme->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'commentsHtml' => $this->renderView('enigme/_comments.html.twig', ['enigme' => $enigme]),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/enigmes/{id}/resolve', name: 'app_enigme_resolve', methods: ['POST'])]
    public function resolve(Enigme $enigme, Request $request): JsonResponse
    {
        if ($this->getParticipantCharacter($enigme->getRaid()) === null) {
            throw $this->createAccessDeniedException('Vous ne participez pas à ce raid.');
        }
        $this->ensureRaidOpen($enigme);

        if (!$this->isCsrfTokenValid('enigme_' . $enigme->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $resolved = $this->enigmeService->toggleResolved($enigme);

        return new JsonResponse([
            'success'   => true,
            'resolved'  => $resolved,
            'updatedAt' => $enigme->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/enigmes/{id}/partial', name: 'app_enigme_partial', methods: ['GET'])]
    public function partial(Enigme $enigme, Request $request): JsonResponse
    {
        if (!$this->getUser() || $this->getParticipantCharacter($enigme->getRaid()) === null) {
            return new JsonResponse(['changed' => false]);
        }

        $updatedAt = $enigme->getUpdatedAt()->format(\DateTimeInterface::ATOM);

        if ($request->query->get('since') === $updatedAt) {
            return new JsonResponse(['changed' => false]);
        }

        return new JsonResponse([
            'changed'      => true,
            'updatedAt'    => $updatedAt,
            'resolved'     => $enigme->isResolved(),
            'imagesHtml'   => $this->renderView('enigme/_images.html.twig', ['enigme' => $enigme]),
            'commentsHtml' => $this->renderView('enigme/_comments.html.twig', ['enigme' => $enigme]),
        ]);
    }

    private function ensureRaidOpen(Enigme $enigme): void
    {
        if ($enigme->getRaid()->getStatus() === RaidStatus::Closed) {
            throw $this->createAccessDeniedException('Ce raid est terminé, aucune modification n\'est possible.');
        }
    }

    private function getParticipantCharacter(Raid $raid): ?Character
    {
        $p = $this->participantRepo->findAcceptedCharacterForUserAndRaid($this->getUser(), $raid);
        return $p?->getCharacter();
    }
}
