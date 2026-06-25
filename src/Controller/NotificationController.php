<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    use CsrfGuardTrait;

    private const PER_PAGE = 15;

    #[Route('/notifications', name: 'app_notifications')]
    public function index(Request $request, NotificationRepository $repo): Response
    {
        $user   = $this->getUser();
        $page   = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $notifications = $repo->findForUser($user, self::PER_PAGE, $offset);
        $hasMore       = ($offset + self::PER_PAGE) < $repo->countForUser($user);

        if ($page === 1) {
            $repo->markAllReadForUser($user);
        }

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'hasMore'       => $hasMore,
            'page'          => $page,
            'nextPage'      => $page + 1,
        ]);
    }

    #[Route('/notifications/{id}/supprimer', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(Notification $notification, Request $request, EntityManagerInterface $em): Response
    {
        if ($notification->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $this->requireCsrfToken('delete_notif_' . $notification->getId(), $request);
        $em->remove($notification);
        $em->flush();

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/notifications/tout-supprimer', name: 'app_notifications_clear', methods: ['POST'])]
    public function clearAll(Request $request, NotificationRepository $repo): Response
    {
        $this->requireCsrfToken('clear_notifications', $request);
        $repo->deleteAllForUser($this->getUser());

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/api/notifications/unread', name: 'api_notifications_unread', methods: ['GET'])]
    public function unreadCount(NotificationRepository $repo): JsonResponse
    {
        return $this->json(['count' => $repo->countUnreadForUser($this->getUser())]);
    }
}
