<?php

namespace App\Tests\Service;

use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Exception\BusinessRuleException;
use App\Service\RaidService;
use App\Tests\Functional\WebTestCaseBase;

class RaidServiceTest extends WebTestCaseBase
{
    private function service(): RaidService
    {
        return static::getContainer()->get(RaidService::class);
    }

    // ─── applyToRaid ──────────────────────────────────────────────────────────

    public function testApplyToPublicRaidSucceeds(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user = $this->makeUser('applicant@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush();
        $raidId = $raid->getId();
        $charId = $char->getId();

        $this->service()->applyToRaid($raid, $char, $user);

        $this->em->clear();
        $reloadedRaid = $this->em->find(Raid::class, $raidId);
        $reloadedChar = $this->em->find(\App\Entity\Character::class, $charId);
        $this->assertTrue($reloadedRaid->isParticipant($reloadedChar));
    }

    public function testApplyToRaidFailsWhenAlreadyParticipant(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user = $this->makeUser('twice@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->makeParticipant($raid, $char, RaidParticipantStatus::Pending);
        $this->flush();
        $this->em->refresh($raid); // recharge la collection participants depuis la DB

        $this->expectException(BusinessRuleException::class);
        $this->service()->applyToRaid($raid, $char, $user);
    }

    public function testApplyToRaidFailsWhenRaidClosed(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid->setStatus(RaidStatus::Closed);

        $user = $this->makeUser('late@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->applyToRaid($raid, $char, $user);
    }

    public function testApplyToRaidFailsWhenWrongServer(): void
    {
        $serverA   = $this->makeServer();
        $serverB   = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $serverA);
        $guild     = $this->makeGuild($owner, $serverA);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user = $this->makeUser('wrongserver@test.com');
        $char = $this->makeCharacter($user, $serverB);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->applyToRaid($raid, $char, $user);
    }

    public function testApplyToRaidFailsWhenFull(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $template  = $this->makeRaidTemplate('Mini');
        $template->setMaxParticipants(1);
        $raid      = (new Raid())->setGuild($guild)->setCreator($ownerChar)->setRaidTemplate($template)->setIsPublic(true);
        $this->em->persist($raid);
        $this->makeParticipant($raid, $ownerChar, RaidParticipantStatus::Accepted);

        $user = $this->makeUser('late@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush();
        $this->em->refresh($raid); // recharge isFull() depuis la DB

        $this->expectException(BusinessRuleException::class);
        $this->service()->applyToRaid($raid, $char, $user);
    }

    public function testApplyToPrivateRaidFailsForNonMember(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: false);

        $outsider = $this->makeUser('outsider@test.com');
        $char     = $this->makeCharacter($outsider, $server);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->applyToRaid($raid, $char, $outsider);
    }

    // ─── acceptParticipant / kickParticipant ──────────────────────────────────

    public function testAcceptParticipantChangesStatusToAccepted(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user        = $this->makeUser('pending@test.com');
        $char        = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Pending);
        $this->flush();

        $this->service()->acceptParticipant($participant);

        $this->assertSame(RaidParticipantStatus::Accepted, $participant->getStatus());
    }

    public function testKickParticipantRemovesIt(): void
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
        $participantId = $participant->getId();

        $this->service()->kickParticipant($participant);

        $this->assertNull($this->em->find(RaidParticipant::class, $participantId));
    }

    public function testApplyToPrivateRaidSucceedsForConfirmedGuildMember(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: false);

        $member     = $this->makeUser('member@test.com');
        $memberChar = $this->makeCharacter($member, $server);
        $this->makeMembership($guild, $memberChar, \App\Entity\MemberStatus::Member);
        $this->flush();
        $this->em->refresh($raid);

        $this->service()->applyToRaid($raid, $memberChar, $member);

        $this->em->refresh($raid);
        $this->assertTrue($raid->isParticipant($memberChar));
    }

    // ─── createRaid ───────────────────────────────────────────────────────────

    public function testCreateRaidPersistsRaidAndAddsCreatorAsAcceptedParticipant(): void
    {
        $server   = $this->makeServer();
        $owner    = $this->makeUser('creator@test.com');
        $char     = $this->makeCharacter($owner, $server);
        $guild    = $this->makeGuild($owner, $server);
        $template = $this->makeRaidTemplate('Raid Alpha');
        $this->flush();

        $raid = new \App\Entity\Raid();
        $this->service()->createRaid($raid, $guild, $char, $template);

        $this->assertNotNull($raid->getId());
        // La collection inversée Raid.participants n'est pas peuplée en mémoire → refresh depuis la DB
        $this->em->refresh($raid);
        $participants = $raid->getParticipants();
        $this->assertCount(1, $participants);
        $this->assertSame(RaidParticipantStatus::Accepted, $participants->first()->getStatus());
        $this->assertSame($char->getId(), $participants->first()->getCharacter()->getId());
    }

    // ─── closeRaid / deleteRaid ───────────────────────────────────────────────

    public function testCloseRaidSetsClosedStatus(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $this->flush();

        $this->service()->closeRaid($raid);

        $this->assertSame(RaidStatus::Closed, $raid->getStatus());
    }

    public function testCloseRaidFailsWhenAlreadyClosed(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid->setStatus(RaidStatus::Closed);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->closeRaid($raid);
    }

    public function testAcceptParticipantFailsWhenAlreadyAccepted(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user        = $this->makeUser('already@test.com');
        $char        = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->acceptParticipant($participant);
    }

    // ─── closeIfExpired ───────────────────────────────────────────────────────

    public function testCloseIfExpiredClosesRaidPastEndTime(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        // Template par défaut : 60 minutes de durée (cf. makeRaidTemplate)
        $raid->setScheduledAt(new \DateTimeImmutable('-2 hours'));
        $this->flush();

        $closed = $this->service()->closeIfExpired($raid);

        $this->assertTrue($closed);
        $this->assertSame(RaidStatus::Closed, $raid->getStatus());
    }

    public function testCloseIfExpiredDoesNothingWhenNotYetExpired(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid->setScheduledAt(new \DateTimeImmutable('+30 minutes'));
        $this->flush();

        $closed = $this->service()->closeIfExpired($raid);

        $this->assertFalse($closed);
        $this->assertSame(RaidStatus::Open, $raid->getStatus());
    }

    public function testCloseIfExpiredDoesNothingWhenAlreadyClosed(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid->setScheduledAt(new \DateTimeImmutable('-2 hours'));
        $raid->setStatus(RaidStatus::Closed);
        $this->flush();

        $closed = $this->service()->closeIfExpired($raid);

        $this->assertFalse($closed);
    }

    public function testCloseIfExpiredDoesNothingWhenNoScheduledAt(): void
    {
        // Raid "ongoing" sans date planifiée : pas de fin attendue, jamais clôturé automatiquement.
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $this->flush();

        $closed = $this->service()->closeIfExpired($raid);

        $this->assertFalse($closed);
        $this->assertSame(RaidStatus::Open, $raid->getStatus());
    }

    public function testDeleteRaidRemovesIt(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $this->flush();
        $raidId = $raid->getId();

        $this->service()->deleteRaid($raid);

        $this->assertNull($this->em->find(Raid::class, $raidId));
    }
}
