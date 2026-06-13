<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidComment;
use App\Exception\BusinessRuleException;
use App\Repository\RaidParticipantRepository;
use App\Service\RaidCommentService;
use App\Traits\CsrfGuardTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/raids')]
class RaidCommentController extends AbstractController
{
    use CsrfGuardTrait;

    public function __construct(
        private readonly RaidParticipantRepository $participantRepo,
        private readonly RaidCommentService $commentService,
    ) {}

    #[Route('/{id}/commenter', name: 'app_raid_comment_new', methods: ['POST'])]
    public function new(Raid $raid, Request $request): Response
    {
        $this->requireCsrfToken('comment_' . $raid->getId(), $request);

        if (!$this->participantRepo->hasApplied($this->getUser(), $raid)) {
            throw $this->createAccessDeniedException('Vous devez postuler au raid pour commenter.');
        }

        $content = trim($request->request->get('content', ''));
        if ($content !== '') {
            $this->commentService->addComment($raid, $this->getUser(), $content);
        }

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
    }

    #[Route('/commentaires/{id}/repondre', name: 'app_raid_comment_reply', methods: ['POST'])]
    public function reply(RaidComment $parent, Request $request): Response
    {
        $this->requireCsrfToken('reply_' . $parent->getId(), $request);

        if (!$this->participantRepo->hasApplied($this->getUser(), $parent->getRaid())) {
            throw $this->createAccessDeniedException('Vous devez postuler au raid pour commenter.');
        }

        $content = trim($request->request->get('content', ''));
        if ($content !== '') {
            try {
                $this->commentService->addReply($parent, $this->getUser(), $content);
            } catch (BusinessRuleException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_raid_show', ['id' => $parent->getRaid()->getId(), '_fragment' => 'commentaires']);
    }

    #[Route('/commentaires/{id}/supprimer', name: 'app_raid_comment_delete', methods: ['POST'])]
    public function delete(RaidComment $comment, Request $request): Response
    {
        $this->requireCsrfToken('delete_comment_' . $comment->getId(), $request);

        $isAuthor  = $comment->getAuthor()->getId() === $this->getUser()->getId();
        $isCreator = $comment->getRaid()->isCreatedBy($this->getUser());

        if (!$isAuthor && !$isCreator) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce commentaire.');
        }

        $this->commentService->deleteComment($comment);

        return $this->redirectToRoute('app_raid_show', ['id' => $comment->getRaid()->getId(), '_fragment' => 'commentaires']);
    }
}
