<?php

namespace App\Tests\Service;

use App\Entity\Character;
use App\Entity\EnigmeComment;
use App\Entity\EnigmeImage;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Exception\BusinessRuleException;
use App\Service\CharacterService;
use App\Tests\Functional\WebTestCaseBase;

class CharacterServiceTest extends WebTestCaseBase
{
    private function service(): CharacterService
    {
        return static::getContainer()->get(CharacterService::class);
    }

    // ─── clearParticipationsForServerChange ───────────────────────────────────

    public function testClearParticipationsRemovesGuildMembership(): void
    {
        $serverA    = $this->makeServer();
        $serverB    = $this->makeServer();
        $owner      = $this->makeUser('owner@test.com');
        $user       = $this->makeUser('member@test.com');
        $char       = $this->makeCharacter($user, $serverA);
        $guild      = $this->makeGuild($owner, $serverA);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Member);
        $this->flush();
        $membershipId = $membership->getId();
        $this->em->refresh($char); // recharge la relation membership depuis la DB

        $char->setServer($serverB);
        $this->service()->clearParticipationsForServerChange($char);
        $this->flush();

        $this->assertNull($this->em->find(GuildMembership::class, $membershipId));
    }

    public function testClearParticipationsFailsWhenLeader(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $char    = $this->makeCharacter($owner, $serverA);
        $guild   = $this->makeGuild($owner, $serverA);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Leader);
        $this->flush();
        $this->em->refresh($char); // recharge la relation membership depuis la DB

        $char->setServer($serverB);

        $this->expectException(BusinessRuleException::class);
        $this->service()->clearParticipationsForServerChange($char);

        // La membership de meneur ne doit pas avoir été supprimée
        $this->assertNotNull($this->em->find(GuildMembership::class, $membership->getId()));
    }

    public function testClearParticipationsRemovesRaidParticipations(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $user    = $this->makeUser('participant@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $guild   = $this->makeGuild($owner, $serverA);
        $raid    = $this->makeRaid($guild, $this->makeCharacter($owner, $serverA));
        $p1      = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();
        $p1Id = $p1->getId();

        $char->setServer($serverB);
        $this->service()->clearParticipationsForServerChange($char);
        $this->flush();

        $this->assertNull($this->em->find(RaidParticipant::class, $p1Id));
    }

    public function testClearParticipationsRemovesEnigmeCommentsAndImages(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $user    = $this->makeUser('author@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $guild   = $this->makeGuild($owner, $serverA);
        $raid    = $this->makeRaid($guild, $this->makeCharacter($owner, $serverA));
        $enigme  = $this->makeEnigme($raid);
        $comment = $this->makeEnigmeComment($enigme, $char);
        $image   = $this->makeEnigmeImage($enigme, $char);
        $this->flush();
        $commentId = $comment->getId();
        $imageId   = $image->getId();

        $char->setServer($serverB);
        $this->service()->clearParticipationsForServerChange($char);
        $this->flush();

        $this->assertNull($this->em->find(EnigmeComment::class, $commentId));
        $this->assertNull($this->em->find(EnigmeImage::class, $imageId));
    }

    public function testClearParticipationsDoesNotTouchOtherCharactersData(): void
    {
        $serverA  = $this->makeServer();
        $serverB  = $this->makeServer();
        $owner    = $this->makeUser('owner@test.com');
        $userA    = $this->makeUser('moving@test.com');
        $userB    = $this->makeUser('staying@test.com');
        $charA    = $this->makeCharacter($userA, $serverA);
        $charB    = $this->makeCharacter($userB, $serverA);
        $guild    = $this->makeGuild($owner, $serverA);
        $raid     = $this->makeRaid($guild, $this->makeCharacter($owner, $serverA));
        $enigme   = $this->makeEnigme($raid);

        $membershipA = $this->makeMembership($guild, $charA, MemberStatus::Member);
        $membershipB = $this->makeMembership($guild, $charB, MemberStatus::Member);
        $participantA = $this->makeParticipant($raid, $charA, RaidParticipantStatus::Accepted);
        $participantB = $this->makeParticipant($raid, $charB, RaidParticipantStatus::Accepted);
        $commentB = $this->makeEnigmeComment($enigme, $charB);
        $imageB   = $this->makeEnigmeImage($enigme, $charB);
        $this->flush();

        $membershipAId  = $membershipA->getId();
        $participantAId = $participantA->getId();
        $membershipBId  = $membershipB->getId();
        $participantBId = $participantB->getId();
        $commentBId     = $commentB->getId();
        $imageBId       = $imageB->getId();
        $this->em->refresh($charA); // recharge la relation membership depuis la DB

        $charA->setServer($serverB);
        $this->service()->clearParticipationsForServerChange($charA);
        $this->flush();

        // Le personnage A a bien tout perdu
        $this->assertNull($this->em->find(GuildMembership::class, $membershipAId));
        $this->assertNull($this->em->find(RaidParticipant::class, $participantAId));

        // Le personnage B (resté sur le serveur d'origine) n'est pas affecté
        $this->assertNotNull($this->em->find(GuildMembership::class, $membershipBId));
        $this->assertNotNull($this->em->find(RaidParticipant::class, $participantBId));
        $this->assertNotNull($this->em->find(EnigmeComment::class, $commentBId));
        $this->assertNotNull($this->em->find(EnigmeImage::class, $imageBId));
    }

    public function testClearParticipationsWithNoMembershipOrParticipationsIsNoop(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $user    = $this->makeUser('lone@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $this->flush();

        $char->setServer($serverB);
        $this->service()->clearParticipationsForServerChange($char);
        $this->flush();

        $this->assertSame($serverB->getId(), $char->getServer()->getId());
    }
}
