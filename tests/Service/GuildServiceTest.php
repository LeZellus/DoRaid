<?php

namespace App\Tests\Service;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Exception\BusinessRuleException;
use App\Service\GuildService;
use App\Tests\Functional\WebTestCaseBase;

class GuildServiceTest extends WebTestCaseBase
{
    private function service(): GuildService
    {
        return static::getContainer()->get(GuildService::class);
    }

    // ─── createGuild ──────────────────────────────────────────────────────────

    public function testCreateGuildSetsLeaderMembership(): void
    {
        $server = $this->makeServer();
        $user   = $this->makeUser('creator@test.com');
        $char   = $this->makeCharacter($user, $server);
        $this->flush();

        $guild = (new Guild())->setName('Ma Guilde')->setServer($server);
        $this->service()->createGuild($guild, $char, $user);

        $this->assertNotNull($guild->getId());
        $this->assertNotEmpty($guild->getSlug());
        $this->em->refresh($char);
        $this->assertSame(MemberStatus::Leader, $char->getMembership()->getStatus());
    }

    public function testCreateGuildGeneratesUniqueSlugOnConflict(): void
    {
        $server = $this->makeServer();
        $user1  = $this->makeUser('u1@test.com');
        $user2  = $this->makeUser('u2@test.com');
        $char1  = $this->makeCharacter($user1, $server);
        $char2  = $this->makeCharacter($user2, $server);
        $this->flush();

        $guild1 = (new Guild())->setName('Les Guerriers')->setServer($server);
        $this->service()->createGuild($guild1, $char1, $user1);

        $guild2 = (new Guild())->setName('Les Guerriers')->setServer($server);
        $this->service()->createGuild($guild2, $char2, $user2);

        $this->assertNotSame($guild1->getSlug(), $guild2->getSlug());
        $this->assertStringStartsWith('les-guerriers', $guild2->getSlug());
    }

    public function testCreateGuildFailsWhenCharacterAlreadyInGuild(): void
    {
        $server  = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $user    = $this->makeUser('busy@test.com');
        $char    = $this->makeCharacter($user, $server);
        $guild   = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $char, MemberStatus::Member);
        $this->flush();
        $this->em->refresh($char); // recharge la relation membership depuis la DB

        $newGuild = (new Guild())->setName('Nouvelle Guilde')->setServer($server);
        $this->expectException(BusinessRuleException::class);
        $this->service()->createGuild($newGuild, $char, $user);
    }

    public function testCreateGuildFailsWhenCharacterOnWrongServer(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $user    = $this->makeUser('mismatch@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $this->flush();

        $guild = (new Guild())->setName('Guilde Serveur B')->setServer($serverB);
        $this->expectException(BusinessRuleException::class);
        $this->service()->createGuild($guild, $char, $user);
    }

    // ─── joinGuild ────────────────────────────────────────────────────────────

    public function testJoinGuildCreatesPendingStatusForNonOwner(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        $user = $this->makeUser('joiner@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush();

        $status = $this->service()->joinGuild($guild, $char, $user);

        $this->assertSame(MemberStatus::Pending, $status);
    }

    public function testJoinGuildCreatesLeaderStatusForOwnerWithoutLeader(): void
    {
        $server = $this->makeServer();
        $owner  = $this->makeUser('owner@test.com');
        $char   = $this->makeCharacter($owner, $server);
        $guild  = $this->makeGuild($owner, $server);
        $this->flush();

        $status = $this->service()->joinGuild($guild, $char, $owner);

        $this->assertSame(MemberStatus::Leader, $status);
    }

    public function testJoinGuildFailsWhenAlreadyInAnyGuild(): void
    {
        $server   = $this->makeServer();
        $owner    = $this->makeUser('owner@test.com');
        $user     = $this->makeUser('member@test.com');
        $char     = $this->makeCharacter($user, $server);
        $guildA   = $this->makeGuild($owner, $server);
        $this->makeMembership($guildA, $char, MemberStatus::Member);
        $guildB   = $this->makeGuild($owner, $server);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->joinGuild($guildB, $char, $user);
    }

    public function testJoinGuildFailsWhenWrongServer(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $user    = $this->makeUser('joiner@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $guild   = $this->makeGuild($owner, $serverB);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->joinGuild($guild, $char, $user);
    }

    // ─── leaveGuild ───────────────────────────────────────────────────────────

    public function testLeaveGuildRemovesMembership(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $user       = $this->makeUser('leaver@test.com');
        $char       = $this->makeCharacter($user, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Member);
        $this->flush();
        $membershipId = $membership->getId();

        $this->service()->leaveGuild($membership);

        $this->assertNull($this->em->find(GuildMembership::class, $membershipId));
    }

    public function testLeaveGuildFailsForLeader(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $char       = $this->makeCharacter($owner, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Leader);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->leaveGuild($membership);
    }

    // ─── approveMembership / rejectMembership ─────────────────────────────────

    public function testApproveMembershipChangesStatusToMember(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $user       = $this->makeUser('pending@test.com');
        $char       = $this->makeCharacter($user, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Pending);
        $this->flush();

        $this->service()->approveMembership($membership);

        $this->assertSame(MemberStatus::Member, $membership->getStatus());
    }

    public function testRejectMembershipRemovesIt(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $user       = $this->makeUser('rejected@test.com');
        $char       = $this->makeCharacter($user, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Pending);
        $this->flush();
        $membershipId = $membership->getId();

        $this->service()->rejectMembership($membership);

        $this->assertNull($this->em->find(GuildMembership::class, $membershipId));
    }

    public function testRejectMembershipFailsForLeader(): void
    {
        $server     = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $char       = $this->makeCharacter($owner, $server);
        $guild      = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Leader);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->rejectMembership($membership);
    }
}
