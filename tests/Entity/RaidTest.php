<?php

namespace App\Tests\Entity;

use App\Entity\Character;
use App\Entity\GuildMembership;
use App\Entity\MemberPunishment;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidTemplate;
use App\Entity\User;

class RaidTest extends EntityTestCase
{
    // ─── isCreatedBy() ─────────────────────────────────────────────────────────

    public function testIsCreatedByReturnsTrueForCreatorUser(): void
    {
        $user    = $this->makeUser(1);
        $creator = (new Character())->setUser($user);
        $raid    = (new Raid())->setCreator($creator);

        $this->assertTrue($raid->isCreatedBy($user));
    }

    public function testIsCreatedByReturnsFalseForOtherUser(): void
    {
        $creator    = (new Character())->setUser($this->makeUser(1));
        $otherUser  = $this->makeUser(2);
        $raid       = (new Raid())->setCreator($creator);

        $this->assertFalse($raid->isCreatedBy($otherUser));
    }

    // ─── isFull() ──────────────────────────────────────────────────────────────

    public function testIsFullReturnsFalseWhenNoParticipants(): void
    {
        $raid = $this->makeRaid(maxParticipants: 3);
        $this->assertFalse($raid->isFull());
    }

    public function testIsFullReturnsFalseWhenBelowMaxAccepted(): void
    {
        $raid = $this->makeRaid(maxParticipants: 3);
        $this->addAccepted($raid);
        $this->addAccepted($raid);

        $this->assertFalse($raid->isFull());
    }

    public function testIsFullReturnsTrueWhenAcceptedEqualsMax(): void
    {
        $raid = $this->makeRaid(maxParticipants: 2);
        $this->addAccepted($raid);
        $this->addAccepted($raid);

        $this->assertTrue($raid->isFull());
    }

    public function testIsFullIgnoresPendingParticipants(): void
    {
        $raid = $this->makeRaid(maxParticipants: 2);
        $this->addPending($raid);
        $this->addPending($raid);
        $this->addPending($raid);

        // Three pending, max is 2 — pending don't count
        $this->assertFalse($raid->isFull());
    }

    public function testIsFullCountsOnlyAccepted(): void
    {
        $raid = $this->makeRaid(maxParticipants: 2);
        $this->addAccepted($raid);
        $this->addPending($raid);

        // One accepted, one pending — not full yet
        $this->assertFalse($raid->isFull());
    }

    // ─── isParticipant() ───────────────────────────────────────────────────────

    public function testIsParticipantReturnsTrueWhenCharacterIsRegistered(): void
    {
        $raid = $this->makeRaid();
        $char = new Character();
        $raid->getParticipants()->add((new RaidParticipant())->setCharacter($char));

        $this->assertTrue($raid->isParticipant($char));
    }

    public function testIsParticipantReturnsFalseWhenCharacterIsNotRegistered(): void
    {
        $raid    = $this->makeRaid();
        $char    = new Character();
        $other   = new Character();
        $raid->getParticipants()->add((new RaidParticipant())->setCharacter($other));

        $this->assertFalse($raid->isParticipant($char));
    }

    public function testIsParticipantReturnsFalseOnEmptyRaid(): void
    {
        $raid = $this->makeRaid();
        $this->assertFalse($raid->isParticipant(new Character()));
    }

    // ─── getAcceptedParticipants() / getPendingParticipants() ──────────────────

    public function testGetAcceptedParticipantsReturnsOnlyAccepted(): void
    {
        $raid = $this->makeRaid();
        $this->addAccepted($raid);
        $this->addAccepted($raid);
        $this->addPending($raid);

        $this->assertCount(2, $raid->getAcceptedParticipants());
    }

    public function testGetPendingParticipantsReturnsOnlyPending(): void
    {
        $raid = $this->makeRaid();
        $this->addAccepted($raid);
        $this->addPending($raid);
        $this->addPending($raid);

        $this->assertCount(2, $raid->getPendingParticipants());
    }

    public function testGetAcceptedAndPendingAreComplementary(): void
    {
        $raid = $this->makeRaid();
        $this->addAccepted($raid);
        $this->addPending($raid);
        $this->addAccepted($raid);

        $total = $raid->getAcceptedParticipants()->count() + $raid->getPendingParticipants()->count();
        $this->assertSame($raid->getParticipants()->count(), $total);
    }

    public function testGetPendingParticipantsSortsPunishedGuildMembersLast(): void
    {
        $raid = $this->makeRaid();

        $punishedMembership = (new GuildMembership())->setGuild($raid->getGuild())->setStatus(\App\Entity\MemberStatus::Member);
        $punishedMembership->getPunishments()->add(
            (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('+1 week'))
        );
        $punishedChar = new Character();
        $this->setProperty($punishedChar, 'membership', $punishedMembership);

        $cleanMembership = (new GuildMembership())->setGuild($raid->getGuild())->setStatus(\App\Entity\MemberStatus::Member);
        $cleanChar       = new Character();
        $this->setProperty($cleanChar, 'membership', $cleanMembership);

        // Le puni candidate en premier ; il doit malgré tout se retrouver après le membre sain.
        $punishedParticipant = (new RaidParticipant())->setRaid($raid)->setCharacter($punishedChar);
        $raid->getParticipants()->add($punishedParticipant);
        $cleanParticipant = (new RaidParticipant())->setRaid($raid)->setCharacter($cleanChar);
        $raid->getParticipants()->add($cleanParticipant);

        $pending = array_values($raid->getPendingParticipants()->toArray());

        $this->assertCount(2, $pending);
        $this->assertSame($cleanParticipant, $pending[0]);
        $this->assertSame($punishedParticipant, $pending[1]);
    }

    // ─── getExpectedEndTime() ──────────────────────────────────────────────────

    public function testGetExpectedEndTimeReturnsNullWhenNoScheduledAt(): void
    {
        $raid = $this->makeRaid(duration: 60);
        // scheduledAt is null by default
        $this->assertNull($raid->getExpectedEndTime());
    }

    public function testGetExpectedEndTimeReturnsNullWhenNoDuration(): void
    {
        $raid = $this->makeRaid(duration: null);
        $raid->setScheduledAt(new \DateTimeImmutable('2026-01-15 20:00:00'));

        $this->assertNull($raid->getExpectedEndTime());
    }

    public function testGetExpectedEndTimeAddsMinutesToScheduledAt(): void
    {
        $raid = $this->makeRaid(duration: 120);
        $raid->setScheduledAt(new \DateTimeImmutable('2026-01-15 20:00:00'));

        $expected = new \DateTimeImmutable('2026-01-15 22:00:00');
        $this->assertEquals($expected, $raid->getExpectedEndTime());
    }

    public function testGetExpectedEndTimeHandlesOvernightRaids(): void
    {
        $raid = $this->makeRaid(duration: 90);
        $raid->setScheduledAt(new \DateTimeImmutable('2026-01-15 23:30:00'));

        $expected = new \DateTimeImmutable('2026-01-16 01:00:00');
        $this->assertEquals($expected, $raid->getExpectedEndTime());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(int $id): User
    {
        $user = new User();
        $this->setId($user, $id);
        return $user;
    }

    private function makeRaid(int $maxParticipants = 5, ?int $duration = null): Raid
    {
        $template = (new RaidTemplate())
            ->setMaxParticipants($maxParticipants)
            ->setMinParticipants(1)
            ->setDuration($duration);
        return (new Raid())->setRaidTemplate($template)->setGuild(new \App\Entity\Guild());
    }

    private function addAccepted(Raid $raid): RaidParticipant
    {
        $p = (new RaidParticipant())->setStatus(RaidParticipantStatus::Accepted)
            ->setRaid($raid)->setCharacter(new Character());
        $raid->getParticipants()->add($p);
        return $p;
    }

    private function addPending(Raid $raid): RaidParticipant
    {
        $p = (new RaidParticipant())->setRaid($raid)->setCharacter(new Character()); // default status is Pending
        $raid->getParticipants()->add($p);
        return $p;
    }
}
