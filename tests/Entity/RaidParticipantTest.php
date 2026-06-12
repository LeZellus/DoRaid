<?php

namespace App\Tests\Entity;

use App\Entity\Character;
use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Entity\Raid;
use App\Entity\RaidParticipant;

class RaidParticipantTest extends EntityTestCase
{
    // ─── isGuildMember() ───────────────────────────────────────────────────────

    public function testIsGuildMemberReturnsTrueWhenCharacterIsMemberOfRaidGuild(): void
    {
        $guild = $this->makeGuild(1);
        $char  = $this->makeCharacterInGuild($guild, MemberStatus::Member);

        $participant = $this->makeParticipant($this->makeRaidInGuild($guild), $char);

        $this->assertTrue($participant->isGuildMember());
    }

    public function testIsGuildMemberReturnsTrueWhenCharacterIsLeaderOfRaidGuild(): void
    {
        $guild = $this->makeGuild(1);
        $char  = $this->makeCharacterInGuild($guild, MemberStatus::Leader);

        $participant = $this->makeParticipant($this->makeRaidInGuild($guild), $char);

        $this->assertTrue($participant->isGuildMember());
    }

    public function testIsGuildMemberReturnsFalseWhenCharacterHasNoMembership(): void
    {
        $guild = $this->makeGuild(1);
        $char  = new Character();

        $participant = $this->makeParticipant($this->makeRaidInGuild($guild), $char);

        $this->assertFalse($participant->isGuildMember());
    }

    public function testIsGuildMemberReturnsFalseWhenCharacterIsInDifferentGuild(): void
    {
        $raidGuild  = $this->makeGuild(1);
        $otherGuild = $this->makeGuild(2);
        $char       = $this->makeCharacterInGuild($otherGuild, MemberStatus::Member);

        $participant = $this->makeParticipant($this->makeRaidInGuild($raidGuild), $char);

        $this->assertFalse($participant->isGuildMember());
    }

    public function testIsGuildMemberReturnsFalseWhenMembershipIsPending(): void
    {
        $guild = $this->makeGuild(1);
        $char  = $this->makeCharacterInGuild($guild, MemberStatus::Pending);

        $participant = $this->makeParticipant($this->makeRaidInGuild($guild), $char);

        $this->assertFalse($participant->isGuildMember());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makeGuild(int $id): Guild
    {
        $guild = new Guild();
        $this->setId($guild, $id);
        return $guild;
    }

    private function makeCharacterInGuild(Guild $guild, MemberStatus $status): Character
    {
        $membership = (new GuildMembership())->setGuild($guild)->setStatus($status);
        $char       = new Character();
        $this->setProperty($char, 'membership', $membership);
        return $char;
    }

    private function makeRaidInGuild(Guild $guild): Raid
    {
        $raid = new Raid();
        $raid->setGuild($guild);
        return $raid;
    }

    private function makeParticipant(Raid $raid, Character $char): RaidParticipant
    {
        return (new RaidParticipant())->setRaid($raid)->setCharacter($char);
    }
}
