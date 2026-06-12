<?php

namespace App\Tests\Functional;

use App\Entity\MemberStatus;

class GuildControllerTest extends WebTestCaseBase
{
    // ─── Accès à la page guilde ────────────────────────────────────────────────

    public function testGuildPageIsAccessibleToAnonymous(): void
    {
        $slug = $this->createGuildWithRaids()['slug'];

        $this->client->request('GET', '/guildes/' . $slug);

        $this->assertResponseIsSuccessful();
    }

    // ─── Visibilité des raids privés ───────────────────────────────────────────

    public function testPublicRaidIsVisibleToNonMembers(): void
    {
        ['slug' => $slug, 'publicName' => $publicName] = $this->createGuildWithRaids();

        $this->client->request('GET', '/guildes/' . $slug);

        $this->assertStringContainsString($publicName, $this->client->getResponse()->getContent());
    }

    public function testPrivateRaidIsHiddenFromNonMembers(): void
    {
        ['slug' => $slug, 'privateName' => $privateName] = $this->createGuildWithRaids();

        $this->client->request('GET', '/guildes/' . $slug);

        $this->assertStringNotContainsString($privateName, $this->client->getResponse()->getContent());
    }

    public function testPrivateRaidIsVisibleToGuildMember(): void
    {
        [
            'slug'        => $slug,
            'privateName' => $privateName,
            'guild'       => $guild,
            'server'      => $server,
        ] = $this->createGuildWithRaids();

        $member     = $this->makeUser('member@test.com');
        $memberChar = $this->makeCharacter($member, $server);
        $this->makeMembership($guild, $memberChar, MemberStatus::Member);
        $this->flush();

        $this->client->loginUser($member);
        $this->client->request('GET', '/guildes/' . $slug);

        $this->assertStringContainsString($privateName, $this->client->getResponse()->getContent());
    }

    // ─── Création de guilde ────────────────────────────────────────────────────

    public function testGuildCreationRedirectsToCharacterCreationWhenNoCharacters(): void
    {
        $user = $this->makeUser('newbie@test.com');
        $this->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/guildes/creer');

        $this->assertResponseRedirects();
        $this->assertStringContainsString('/personnages/creer', $this->client->getResponse()->headers->get('location'));
    }

    public function testGuildCreationFailsWhenCharacterIsOnWrongServer(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $user    = $this->makeUser('creator@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/guildes/creer');
        $token   = $crawler->filter('input[name="guild[_token]"]')->attr('value');

        $this->client->request('POST', '/guildes/creer', [
            'guild'        => ['name' => 'Guilde Test', 'server' => $serverB->getId(), '_token' => $token],
            'character_id' => $char->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('pas sur le serveur', $this->client->getResponse()->getContent());
    }

    public function testGuildCreationSucceedsWithValidCharacter(): void
    {
        $server = $this->makeServer();
        $user   = $this->makeUser('founder@test.com');
        $char   = $this->makeCharacter($user, $server);
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/guildes/creer');
        $token   = $crawler->filter('input[name="guild[_token]"]')->attr('value');

        $this->client->request('POST', '/guildes/creer', [
            'guild'        => ['name' => 'Les Conquérants', 'server' => $server->getId(), '_token' => $token],
            'character_id' => $char->getId(),
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Les Conquérants', $this->client->getResponse()->getContent());
    }

    public function testGuildCreationFailsWhenNoCharacterSelected(): void
    {
        $server = $this->makeServer();
        $user   = $this->makeUser('founder@test.com');
        $this->makeCharacter($user, $server);
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/guildes/creer');
        $token   = $crawler->filter('input[name="guild[_token]"]')->attr('value');

        $this->client->request('POST', '/guildes/creer', [
            'guild' => ['name' => 'Guilde Sans Chef', 'server' => $server->getId(), '_token' => $token],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Sélectionnez un personnage', $this->client->getResponse()->getContent());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crée une guilde avec un raid public et un raid privé aux noms distinctifs.
     * Retourne ['slug', 'publicName', 'privateName', 'guild', 'server'].
     */
    private function createGuildWithRaids(): array
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        $publicName  = 'RAID-PUBLIC-' . uniqid('', true);
        $privateName = 'RAID-PRIVE-' . uniqid('', true);

        $this->makeRaid($guild, $ownerChar, isPublic: true,  templateName: $publicName);
        $this->makeRaid($guild, $ownerChar, isPublic: false, templateName: $privateName);
        $this->flush();

        return [
            'slug'        => $guild->getSlug(),
            'publicName'  => $publicName,
            'privateName' => $privateName,
            'guild'       => $guild,
            'server'      => $server,
        ];
    }
}
