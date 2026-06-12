<?php

namespace App\Tests\Entity;

use App\Entity\Character;
use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;

class CharacterTest extends EntityTestCase
{
    // ─── isConfirmedMemberOf() ─────────────────────────────────────────────────

    public function testIsConfirmedMemberOfReturnsFalseWhenNoMembership(): void
    {
        $char  = new Character();
        $guild = $this->makeGuild(1);

        $this->assertFalse($char->isConfirmedMemberOf($guild));
    }

    public function testIsConfirmedMemberOfReturnsFalseWhenMembershipIsPending(): void
    {
        $guild = $this->makeGuild(1);
        $char  = $this->makeCharacterInGuild($guild, MemberStatus::Pending);

        $this->assertFalse($char->isConfirmedMemberOf($guild));
    }

    public function testIsConfirmedMemberOfReturnsFalseWhenMembershipIsForDifferentGuild(): void
    {
        $guild1 = $this->makeGuild(1);
        $guild2 = $this->makeGuild(2);

        // Character is a confirmed member of guild2, not guild1
        $char = $this->makeCharacterInGuild($guild2, MemberStatus::Member);

        $this->assertFalse($char->isConfirmedMemberOf($guild1));
    }

    public function testIsConfirmedMemberOfReturnsTrueWhenMember(): void
    {
        $guild = $this->makeGuild(1);
        $char  = $this->makeCharacterInGuild($guild, MemberStatus::Member);

        $this->assertTrue($char->isConfirmedMemberOf($guild));
    }

    public function testIsConfirmedMemberOfReturnsTrueWhenLeader(): void
    {
        $guild = $this->makeGuild(1);
        $char  = $this->makeCharacterInGuild($guild, MemberStatus::Leader);

        $this->assertTrue($char->isConfirmedMemberOf($guild));
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
}
