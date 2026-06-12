<?php

namespace App\Controller;

use App\Entity\Character;
use App\Entity\Enigme;
use App\Entity\EnigmeComment;
use App\Entity\EnigmeImage;
use App\Entity\Raid;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EnigmeController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {}

    #[Route('/enigmes/{id}', name: 'app_enigme_show', methods: ['GET'])]
    public function show(Enigme $enigme): Response
    {
        $canComment = $this->getUser() !== null && $this->getParticipantCharacter($enigme->getRaid()) !== null;

        return $this->render('enigme/show.html.twig', [
            'enigme'      => $enigme,
            'canComment'  => $canComment,
            'raidClosed'  => $enigme->getRaid()->getStatus() === RaidStatus::Closed,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/enigmes/{id}/images', name: 'app_enigme_upload_image', methods: ['POST'])]
    public function uploadImage(Enigme $enigme, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->ensureParticipant($enigme);
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

        if ($enigme->getImages()->count() >= 20) {
            return new JsonResponse(['success' => false, 'error' => 'Limite de 20 images par énigme atteinte.'], 400);
        }

        $uploadDir = $this->projectDir . '/public/uploads/enigmes/' . $enigme->getId();
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext      = $file->guessExtension() ?? 'png';
        $filename = uniqid('img_', true) . '.' . $ext;
        $file->move($uploadDir, $filename);

        $image = (new EnigmeImage())
            ->setEnigme($enigme)
            ->setFilePath($filename)
            ->setAddedBy($this->getParticipantCharacter($enigme->getRaid()));

        $em->persist($image);
        $enigme->touch();
        $em->flush();
        $em->refresh($enigme);

        return new JsonResponse([
            'success'    => true,
            'updatedAt'  => $enigme->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'imagesHtml' => $this->renderView('enigme/_images.html.twig', ['enigme' => $enigme]),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/enigmes/{id}/comments', name: 'app_enigme_add_comment', methods: ['POST'])]
    public function addComment(Enigme $enigme, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($this->getParticipantCharacter($enigme->getRaid()) === null) {
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

        $comment = (new EnigmeComment())
            ->setEnigme($enigme)
            ->setContent($content)
            ->setAuthor($this->getParticipantCharacter($enigme->getRaid()));

        $em->persist($comment);
        $enigme->touch();
        $em->flush();
        $em->refresh($enigme);

        return new JsonResponse([
            'success'      => true,
            'updatedAt'    => $enigme->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'commentsHtml' => $this->renderView('enigme/_comments.html.twig', ['enigme' => $enigme]),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/enigmes/{id}/resolve', name: 'app_enigme_resolve', methods: ['POST'])]
    public function resolve(Enigme $enigme, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->ensureParticipant($enigme);
        $this->ensureRaidOpen($enigme);

        if (!$this->isCsrfTokenValid('enigme_' . $enigme->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $enigme->setResolved(!$enigme->isResolved());
        $enigme->touch();
        $em->flush();

        return new JsonResponse([
            'success'   => true,
            'resolved'  => $enigme->isResolved(),
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

    private function ensureParticipant(Enigme $enigme): void
    {
        if ($this->getParticipantCharacter($enigme->getRaid()) === null) {
            throw $this->createAccessDeniedException('Vous ne participez pas à ce raid.');
        }
    }

    private function ensureRaidOpen(Enigme $enigme): void
    {
        if ($enigme->getRaid()->getStatus() === RaidStatus::Closed) {
            throw $this->createAccessDeniedException('Ce raid est terminé, aucune modification n\'est possible.');
        }
    }

    private function getParticipantCharacter(Raid $raid): ?Character
    {
        $userId = (int) $this->getUser()->getId();
        foreach ($raid->getParticipants() as $p) {
            if (
                $p->getStatus() === RaidParticipantStatus::Accepted
                && (int) $p->getCharacter()->getUser()->getId() === $userId
            ) {
                return $p->getCharacter();
            }
        }
        return null;
    }
}
