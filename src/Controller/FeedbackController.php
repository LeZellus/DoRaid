<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Entity\FeedbackType;
use App\Repository\FeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/feedback')]
class FeedbackController extends AbstractController
{
    #[Route('/new', name: 'app_feedback_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('feedback_new', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirect($request->headers->get('referer', '/'));
        }

        $type  = FeedbackType::tryFrom($request->request->get('type', ''));
        $title = trim($request->request->get('title', ''));

        if (!$type || $title === '' || mb_strlen($title) > 200) {
            $this->addFlash('error', 'Formulaire invalide.');
            return $this->redirect($request->headers->get('referer', '/'));
        }

        $description = trim($request->request->get('description', ''));
        $page        = mb_substr(trim($request->request->get('page', '')), 0, 500);

        $feedback = new Feedback();
        $feedback->setType($type);
        $feedback->setTitle($title);
        $feedback->setDescription($description !== '' ? $description : null);
        $feedback->setPage($page !== '' ? $page : null);
        $feedback->setUser($this->getUser());

        $em->persist($feedback);
        $em->flush();

        $this->addFlash('success', 'Merci ! Votre ' . ($type === FeedbackType::Bug ? 'rapport de bug' : 'suggestion') . ' a bien été envoyé.');

        return $this->redirect($request->headers->get('referer', '/'));
    }
}
