<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidComment;
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
    #[Route('/{id}/commenter', name: 'app_raid_comment_new', methods: ['POST'])]
    public function new(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('comment_' . $raid->getId(), $request->request->get('_token'))) {
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
        );
        $em->flush();

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
    }

    #[Route('/commentaires/{id}/repondre', name: 'app_raid_comment_reply', methods: ['POST'])]
    public function reply(RaidComment $parent, Request $request, EntityManagerInterface $em): Response
    {
        $raid = $parent->getRaid();

        if (!$this->isCsrfTokenValid('reply_' . $parent->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
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

        if (!$this->isCsrfTokenValid('delete_comment_' . $comment->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $isAuthor  = $comment->getAuthor()->getId() === $this->getUser()->getId();
        $isCreator = $raid->getCreator()->getUser()->getId() === $this->getUser()->getId();

        if (!$isAuthor && !$isCreator) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($comment);
        $em->flush();

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'commentaires']);
    }
}
