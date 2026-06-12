<?php

namespace App\Tests\Functional;

use App\Entity\Guild;
use App\Entity\MemberStatus;
use App\Entity\Server;

class RaidControllerTest extends WebTestCaseBase
{
    // ─── Visibilité d'un raid public ───────────────────────────────────────────

    public function testPublicRaidIsAccessibleToAnonymous(): void
    {
        $raidId = $this->createRaid(isPublic: true);

        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
    }

    // ─── Visibilité d'un raid privé ────────────────────────────────────────────

    public function testPrivateRaidRedirectsAnonymousToLogin(): void
    {
        $raidId = $this->createRaid(isPublic: false);

        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseRedirects();
        $this->assertStringContainsString(
            '/connexion',
            $this->client->getResponse()->headers->get('location'),
        );
    }

    public function testPrivateRaidRedirectsNonMemberToGuildPage(): void
    {
        [$raidId, $guildSlug] = $this->createRaidWithGuild(isPublic: false);

        $nonMember = $this->makeUser('nonmember@test.com');
        $this->flush();

        $this->client->loginUser($nonMember);
        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseRedirects();
        $this->assertStringContainsString(
            '/guildes/' . $guildSlug,
            $this->client->getResponse()->headers->get('location'),
        );
    }

    public function testPrivateRaidIsAccessibleToGuildMember(): void
    {
        [$raidId, , $guild, $server] = $this->createRaidWithGuild(isPublic: false);

        $member     = $this->makeUser('member@test.com');
        $memberChar = $this->makeCharacter($member, $server);
        $this->makeMembership($guild, $memberChar, MemberStatus::Member);
        $this->flush();

        $this->client->loginUser($member);
        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
    }

    public function testPrivateRaidIsAccessibleToCreatorEvenWithoutGuildMembership(): void
    {
        $server       = $this->makeServer();
        $ownerUser    = $this->makeUser('owner@test.com');
        $ownerChar    = $this->makeCharacter($ownerUser, $server);
        $guild        = $this->makeGuild($ownerUser, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        // Le créateur du raid n'a aucun membership dans la guilde
        $creatorUser  = $this->makeUser('creator@test.com');
        $creatorChar  = $this->makeCharacter($creatorUser, $server);
        $raid         = $this->makeRaid($guild, $creatorChar, isPublic: false);
        $this->flush(); // IDs disponibles seulement après flush
        $raidId       = $raid->getId();

        $this->client->loginUser($creatorUser);
        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
    }

    // ─── Candidature ───────────────────────────────────────────────────────────

    public function testApplySucceedsForEligibleUser(): void
    {
        [$raidId, , , $server] = $this->createRaidWithGuild(isPublic: true);

        $user = $this->makeUser('applicant@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush(); // IDs disponibles seulement après flush
        $charId = $char->getId();

        $this->client->loginUser($user);

        // GET la page pour obtenir le token CSRF depuis le formulaire rendu
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="candidater"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/' . $raidId . '/candidater', [
            '_token'       => $token,
            'character_id' => $charId,
        ]);

        $this->assertResponseStatusCodeSame(302);
        $this->assertStringContainsString(
            '/raids/' . $raidId,
            $this->client->getResponse()->headers->get('location'),
        );
    }

    public function testApplyShowsErrorWhenNoCharacterOnSameServer(): void
    {
        [$raidId] = $this->createRaidWithGuild(isPublic: true);

        // L'utilisateur n'a aucun personnage sur le serveur du raid
        $user = $this->makeUser('nochar@test.com');
        $this->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        // Le message d'absence de personnage sur le serveur doit apparaître
        $this->assertStringContainsString('aucun personnage', $crawler->html());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function createRaid(bool $isPublic): int
    {
        return $this->createRaidWithGuild($isPublic)[0];
    }

    /**
     * Crée serveur, guilde (avec leader), et un raid.
     * Retourne [raidId, guildSlug, guild, server].
     */
    private function createRaidWithGuild(bool $isPublic): array
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: $isPublic);
        $this->flush();

        return [$raid->getId(), $guild->getSlug(), $guild, $server];
    }
}
