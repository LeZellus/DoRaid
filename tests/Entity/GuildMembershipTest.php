<?php

namespace App\Tests\Entity;

use App\Entity\GuildMembership;
use App\Entity\MemberPunishment;

class GuildMembershipTest extends EntityTestCase
{
    // ─── isPunished() / getActivePunishment() ──────────────────────────────────

    public function testIsPunishedReturnsFalseWithoutPunishments(): void
    {
        $membership = new GuildMembership();

        $this->assertFalse($membership->isPunished());
        $this->assertNull($membership->getActivePunishment());
    }

    public function testIsPunishedReturnsFalseWhenOnlyPunishmentHasExpired(): void
    {
        $membership = new GuildMembership();
        $membership->getPunishments()->add(
            (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('-1 day'))
        );

        $this->assertFalse($membership->isPunished());
    }

    public function testIsPunishedReturnsTrueWithActiveTemporaryPunishment(): void
    {
        $membership = new GuildMembership();
        $active     = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('+1 week'));
        $membership->getPunishments()->add($active);

        $this->assertTrue($membership->isPunished());
        $this->assertSame($active, $membership->getActivePunishment());
    }

    public function testGetActivePunishmentReturnsFurthestExpiryAmongActiveOnes(): void
    {
        $membership = new GuildMembership();
        $soon       = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $later      = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('+1 month'));
        $expired    = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $membership->getPunishments()->add($soon);
        $membership->getPunishments()->add($later);
        $membership->getPunishments()->add($expired);

        $this->assertSame($later, $membership->getActivePunishment());
    }

    public function testGetActivePunishmentPrefersPermanentOverTemporary(): void
    {
        $membership = new GuildMembership();
        $temporary  = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('+1 month'));
        $permanent  = (new MemberPunishment())->setExpiresAt(null);
        $membership->getPunishments()->add($temporary);
        $membership->getPunishments()->add($permanent);

        $active = $membership->getActivePunishment();

        $this->assertSame($permanent, $active);
        $this->assertTrue($active->isPermanent());
    }
}
