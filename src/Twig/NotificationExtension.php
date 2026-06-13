<?php

namespace App\Twig;

use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $repo,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_unread_count', $this->unreadCount(...)),
        ];
    }

    public function unreadCount(): int
    {
        $user = $this->security->getUser();
        return $user ? $this->repo->countUnreadForUser($user) : 0;
    }
}
