<?php

namespace App\Tests\Functional;

use App\Repository\NotificationRepository;

class NotificationControllerTest extends WebTestCaseBase
{
    // ─── /notifications ───────────────────────────────────────────────────────

    public function testNotificationsPageRequiresAuth(): void
    {
        $this->client->request('GET', '/notifications');

        $this->assertResponseRedirects();
        $this->assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('location'));
    }

    public function testNotificationsPageLoadsForAuthenticatedUser(): void
    {
        $user = $this->makeUser('user@test.com');
        $this->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/notifications');

        $this->assertResponseIsSuccessful();
    }

    public function testNotificationsPageShowsEmptyState(): void
    {
        $user = $this->makeUser('user@test.com');
        $this->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/notifications');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('p.text-sm'); // empty-state paragraph
    }

    public function testNotificationsPageMarksAllAsRead(): void
    {
        $user = $this->makeUser('user@test.com');
        $this->makeNotification($user, 'participation_pending');
        $this->makeNotification($user, 'membership_pending');
        $this->flush();

        $repo = static::getContainer()->get(NotificationRepository::class);
        $this->assertSame(2, $repo->countUnreadForUser($user));

        $this->client->loginUser($user);
        $this->client->request('GET', '/notifications');

        $this->assertResponseIsSuccessful();
        $this->em->clear();
        $freshUser = $this->em->find(\App\Entity\User::class, $user->getId());
        $this->assertSame(0, $repo->countUnreadForUser($freshUser));
    }

    public function testNotificationsPageDisplaysOnlyUserNotifications(): void
    {
        $user  = $this->makeUser('user@test.com');
        $other = $this->makeUser('other@test.com');
        $this->makeNotification($user, 'participation_accepted');
        $this->makeNotification($other, 'participation_pending');
        $this->flush();

        $repo = static::getContainer()->get(NotificationRepository::class);
        // Sanity check: user has exactly 1 notification, other has 1
        $this->assertCount(1, $repo->findForUser($user));
        $this->assertCount(1, $repo->findForUser($other));

        $this->client->loginUser($user);
        $this->client->request('GET', '/notifications');

        $this->assertResponseIsSuccessful();
        // The page should not show an empty state (user has 1 notification)
        $this->assertSelectorNotExists('p.text-sm');
    }

    // ─── /api/notifications/unread ────────────────────────────────────────────

    public function testUnreadCountApiRequiresAuth(): void
    {
        $this->client->request('GET', '/api/notifications/unread');

        $this->assertResponseRedirects();
    }

    public function testUnreadCountApiReturnsZeroWithNoNotifications(): void
    {
        $user = $this->makeUser('user@test.com');
        $this->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/notifications/unread');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['count']);
    }

    public function testUnreadCountApiReturnsCorrectCount(): void
    {
        $user  = $this->makeUser('user@test.com');
        $other = $this->makeUser('other@test.com');
        $this->makeNotification($user, 'participation_pending');
        $this->makeNotification($user, 'participation_accepted');
        $this->makeNotification($other, 'membership_pending');
        $this->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/notifications/unread');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(2, $data['count']);
    }

    public function testUnreadCountApiExcludesReadNotifications(): void
    {
        $user  = $this->makeUser('user@test.com');
        $read  = $this->makeNotification($user, 'participation_accepted');
        $read->markRead();
        $this->makeNotification($user, 'participation_pending');
        $this->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/notifications/unread');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['count']);
    }
}
