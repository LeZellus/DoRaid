<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications')]
    public function index(NotificationRepository $repo): Response
    {
        $user = $this->getUser();
        $notifications = $repo->findForUser($user);
        $repo->markAllReadForUser($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}
