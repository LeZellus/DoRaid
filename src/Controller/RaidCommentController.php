<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidComment;
use App\Repository\RaidParticipantRepository;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(private readonly RaidParticipantRepository $participantRepo) {}

    #[Route('/{id}/commenter', name: 'app_raid_comment_new', methods: ['POST'])]
    public function new(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireCsrfToken('comment_' . $raid->getId(), $request);

        if (!$this->hasApplied($raid)) {
            throw $this->createAccessDeniedException('Vous devez postuler au raid pour commenter.');
        }

        $content = trim($request->request->get('content', ''));
        if ($content === '') {
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
        }

        $em->persist((new RaidComment())
            ->setRaid($raid)
            ->setAuthor($this->getUser())
            ->setContent($content)
        );
        $em->flush();

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
    }

    #[Route('/commentaires/{id}/repondre', name: 'app_raid_comment_reply', methods: ['POST'])]
    public function reply(RaidComment $parent, Request $request, EntityManagerInterface $em): Response
    {
        $raid = $parent->getRaid();

        $this->requireCsrfToken('reply_' . $parent->getId(), $request);

        if (!$this->hasApplied($raid)) {
            throw $this->createAccessDeniedException('Vous devez postuler au raid pour commenter.');
        }

        // Replies only on root-level comments — prevents unbounded nesting
        if ($parent->getParent() !== null) {
            throw $this->createAccessDeniedException();
        }

        $content = trim($request->request->get('content', ''));
        if ($content === '') {
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
        }

        $em->persist((new RaidComment())
            ->setRaid($raid)
            ->setAuthor($this->getUser())
            ->setContent($content)
            ->setParent($parent)
        );
        $em->flush();

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
    }

    #[Route('/commentaires/{id}/supprimer', name: 'app_raid_comment_delete', methods: ['POST'])]
    public function delete(RaidComment $comment, Request $request, EntityManagerInterface $em): Response
    {
        $raid = $comment->getRaid();

        $this->requireCsrfToken('delete_comment_' . $comment->getId(), $request);

        $isAuthor  = $comment->getAuthor()->getId() === $this->getUser()->getId();
        $isCreator = $raid->isCreatedBy($this->getUser());

        if (!$isAuthor && !$isCreator) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($comment);
        $em->flush();

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
    }

    private function hasApplied(Raid $raid): bool
    {
        return $this->participantRepo->hasApplied($this->getUser(), $raid);
    }
}
