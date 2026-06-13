<?php

namespace App\Tests\Service;

use App\Entity\MemberStatus;
use App\Entity\Notification;
use App\Entity\RaidParticipantStatus;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Tests\Functional\WebTestCaseBase;

class NotificationServiceTest extends WebTestCaseBase
{
    private function service(): NotificationService
    {
        return static::getContainer()->get(NotificationService::class);
    }

    private function repo(): NotificationRepository
    {
        return static::getContainer()->get(NotificationRepository::class);
    }

    // ─── notifyParticipationReceived ──────────────────────────────────────────

    public function testNotifyParticipationReceivedCreatesNotificationForCreator(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $applicant     = $this->makeUser('applicant@test.com');
        $applicantChar = $this->makeCharacter($applicant, $server);
        $participant   = $this->makeParticipant($raid, $applicantChar, RaidParticipantStatus::Pending);
        $this->flush();

        $this->service()->notifyParticipationReceived($participant);
        $this->em->flush();

        $notifications = $this->repo()->findForUser($owner);
        $this->assertCount(1, $notifications);
        $this->assertSame('participation_pending', $notifications[0]->getType());
        $this->assertStringContainsString($applicantChar->getName(), $notifications[0]->getMessage());
        $this->assertNotNull($notifications[0]->getLink());
    }

    public function testNotifyParticipationReceivedDoesNotNotifyApplicant(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $applicant     = $this->makeUser('applicant@test.com');
        $applicantChar = $this->makeCharacter($applicant, $server);
        $participant   = $this->makeParticipant($raid, $applicantChar, RaidParticipantStatus::Pending);
        $this->flush();

        $this->service()->notifyParticipationReceived($participant);
        $this->em->flush();

        $this->assertEmpty($this->repo()->findForUser($applicant));
    }

    // ─── notifyParticipationAccepted ──────────────────────────────────────────

    public function testNotifyParticipationAcceptedCreatesNotificationForParticipantUser(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user        = $this->makeUser('accepted@test.com');
        $char        = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        $this->service()->notifyParticipationAccepted($participant);
        $this->em->flush();

        $notifications = $this->repo()->findForUser($user);
        $this->assertCount(1, $notifications);
        $this->assertSame('participation_accepted', $notifications[0]->getType());
        $this->assertStringContainsString($char->getName(), $notifications[0]->getMessage());
    }

    // ─── notifyParticipationKicked ────────────────────────────────────────────

    public function testNotifyParticipationKickedCreatesNotificationForParticipantUser(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user        = $this->makeUser('kicked@test.com');
        $char        = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        $this->service()->notifyParticipationKicked($participant);
        $this->em->flush();

        $notifications = $this->repo()->findForUser($user);
        $this->assertCount(1, $notifications);
        $this->assertSame('participation_kicked', $notifications[0]->getType());
        $this->assertStringContainsString($char->getName(), $notifications[0]->getMessage());
    }

    // ─── notifyMembershipRequested ────────────────────────────────────────────

    public function testNotifyMembershipRequestedNotifiesLeader(): void
    {
        $server     = $this->makeServer();
        $leader     = $this->makeUser('leader@test.com');
        $leaderChar = $this->makeCharacter($leader, $server);
        $guild      = $this->makeGuild($leader, $server);
        $this->makeMembership($guild, $leaderChar, MemberStatus::Leader);

        $applicant     = $this->makeUser('applicant@test.com');
        $applicantChar = $this->makeCharacter($applicant, $server);
        $membership    = $this->makeMembership($guild, $applicantChar, MemberStatus::Pending);
        $this->flush();
        $this->em->refresh($guild);

        $this->service()->notifyMembershipRequested($membership);
        $this->em->flush();

        $notifications = $this->repo()->findForUser($leader);
        $this->assertCount(1, $notifications);
        $this->assertSame('membership_pending', $notifications[0]->getType());
        $this->assertStringContainsString($applicantChar->getName(), $notifications[0]->getMessage());
    }

    public function testNotifyMembershipRequestedNotifiesAllLeaders(): void
    {
        $server      = $this->makeServer();
        $leader1     = $this->makeUser('leader1@test.com');
        $leader2     = $this->makeUser('leader2@test.com');
        $leaderChar1 = $this->makeCharacter($leader1, $server);
        $leaderChar2 = $this->makeCharacter($leader2, $server);
        $guild       = $this->makeGuild($leader1, $server);
        $this->makeMembership($guild, $leaderChar1, MemberStatus::Leader);
        $this->makeMembership($guild, $leaderChar2, MemberStatus::Leader);

        $applicant     = $this->makeUser('applicant@test.com');
        $applicantChar = $this->makeCharacter($applicant, $server);
        $membership    = $this->makeMembership($guild, $applicantChar, MemberStatus::Pending);
        $this->flush();
        $this->em->refresh($guild);

        $this->service()->notifyMembershipRequested($membership);
        $this->em->flush();

        $this->assertCount(1, $this->repo()->findForUser($leader1));
        $this->assertCount(1, $this->repo()->findForUser($leader2));
        $this->assertEmpty($this->repo()->findForUser($applicant));
    }

    public function testNotifyMembershipRequestedSkipsNonLeaders(): void
    {
        $server      = $this->makeServer();
        $member      = $this->makeUser('member@test.com');
        $memberChar  = $this->makeCharacter($member, $server);
        $guild       = $this->makeGuild($member, $server);
        $this->makeMembership($guild, $memberChar, MemberStatus::Member);

        $applicant     = $this->makeUser('applicant@test.com');
        $applicantChar = $this->makeCharacter($applicant, $server);
        $membership    = $this->makeMembership($guild, $applicantChar, MemberStatus::Pending);
        $this->flush();
        $this->em->refresh($guild);

        $this->service()->notifyMembershipRequested($membership);
        $this->em->flush();

        $this->assertEmpty($this->repo()->findForUser($member));
    }

    // ─── notifyMembershipApproved ─────────────────────────────────────────────

    public function testNotifyMembershipApprovedCreatesNotificationForMember(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $user       = $this->makeUser('member@test.com');
        $char       = $this->makeCharacter($user, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Member);
        $this->flush();

        $this->service()->notifyMembershipApproved($membership);
        $this->em->flush();

        $notifications = $this->repo()->findForUser($user);
        $this->assertCount(1, $notifications);
        $this->assertSame('membership_approved', $notifications[0]->getType());
        $this->assertStringContainsString($char->getName(), $notifications[0]->getMessage());
        $this->assertStringContainsString($guild->getName(), $notifications[0]->getMessage());
    }

    // ─── notifyMembershipRejected ─────────────────────────────────────────────

    public function testNotifyMembershipRejectedCreatesNotificationForMember(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $user       = $this->makeUser('rejected@test.com');
        $char       = $this->makeCharacter($user, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Pending);
        $this->flush();

        $this->service()->notifyMembershipRejected($membership);
        $this->em->flush();

        $notifications = $this->repo()->findForUser($user);
        $this->assertCount(1, $notifications);
        $this->assertSame('membership_rejected', $notifications[0]->getType());
        $this->assertStringContainsString($char->getName(), $notifications[0]->getMessage());
        $this->assertStringContainsString($guild->getName(), $notifications[0]->getMessage());
    }

    // ─── Notifications are unread by default ─────────────────────────────────

    public function testNewNotificationsAreUnread(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user        = $this->makeUser('user@test.com');
        $char        = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        $this->service()->notifyParticipationAccepted($participant);
        $this->em->flush();

        $this->assertSame(1, $this->repo()->countUnreadForUser($user));
    }
}
