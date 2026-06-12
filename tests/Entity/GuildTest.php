<?php

namespace App\Tests\Entity;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;

class GuildTest extends EntityTestCase
{
    // ─── hasLeader() ───────────────────────────────────────────────────────────

    public function testHasLeaderReturnsFalseWhenNoMemberships(): void
    {
        $guild = new Guild();
        $this->assertFalse($guild->hasLeader());
    }

    public function testHasLeaderReturnsFalseWhenOnlyPendingAndMembers(): void
    {
        $guild = new Guild();
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Pending));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Member));

        $this->assertFalse($guild->hasLeader());
    }

    public function testHasLeaderReturnsTrueWhenAtLeastOneLeaderExists(): void
    {
        $guild = new Guild();
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Pending));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Leader));

        $this->assertTrue($guild->hasLeader());
    }

    // ─── getConfirmed() ────────────────────────────────────────────────────────

    public function testGetConfirmedReturnsEmptyWhenNoMemberships(): void
    {
        $guild = new Guild();
        $this->assertCount(0, $guild->getConfirmed());
    }

    public function testGetConfirmedExcludesPendingMemberships(): void
    {
        $guild = new Guild();
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Pending));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Pending));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Member));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Leader));

        $this->assertCount(2, $guild->getConfirmed());
    }

    public function testGetConfirmedReturnsAllWhenNoPendingMemberships(): void
    {
        $guild = new Guild();
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Member));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Leader));

        $this->assertCount(2, $guild->getConfirmed());
    }

    // ─── getPending() ──────────────────────────────────────────────────────────

    public function testGetPendingReturnsEmptyWhenNoMemberships(): void
    {
        $guild = new Guild();
        $this->assertCount(0, $guild->getPending());
    }

    public function testGetPendingReturnsOnlyPendingMemberships(): void
    {
        $guild = new Guild();
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Pending));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Pending));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Member));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Leader));

        $this->assertCount(2, $guild->getPending());
    }

    public function testGetPendingReturnsEmptyWhenAllConfirmed(): void
    {
        $guild = new Guild();
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Member));
        $guild->getMemberships()->add($this->makeMembership(MemberStatus::Leader));

        $this->assertCount(0, $guild->getPending());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makeMembership(MemberStatus $status): GuildMembership
    {
        return (new GuildMembership())->setStatus($status);
    }
}
