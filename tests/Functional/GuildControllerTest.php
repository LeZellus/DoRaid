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
