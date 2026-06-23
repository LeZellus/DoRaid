<?php

namespace App\Tests\Functional;

use App\Entity\MemberStatus;

class CharacterControllerTest extends WebTestCaseBase
{
    // ─── Création de personnage ────────────────────────────────────────────────

    public function testCreateCharacterSucceeds(): void
    {
        $server    = $this->makeServer();
        $gameClass = $this->makeGameClass();
        $user      = $this->makeUser('newchar@test.com');
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/personnages/creer');
        $token   = $crawler->filter('input[name="character[_token]"]')->attr('value');

        $this->client->request('POST', '/personnages/creer', [
            'character' => [
                'name'              => 'Darkhero',
                'gameClass'         => $gameClass->getId(),
                'server'            => $server->getId(),
                'level'             => 60,
                'optimizationLevel' => 'haute',
                '_token'            => $token,
            ],
        ]);

        $this->assertResponseRedirects('/personnages');
    }

    public function testDuplicateCharacterNameOnSameServerShowsFormError(): void
    {
        $server    = $this->makeServer();
        $gameClass = $this->makeGameClass();
        $user      = $this->makeUser('dup@test.com');
        $this->makeCharacter($user, $server); // nom généré par uniqid, on crée un second avec le même nom via le form
        $this->flush();
        $this->em->clear();

        // On récupère le nom du personnage existant pour le réutiliser
        $existing = $this->charRepo()->findByUser($user)[0];
        $existingName = $existing->getName();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/personnages/creer');
        $token   = $crawler->filter('input[name="character[_token]"]')->attr('value');

        $this->client->request('POST', '/personnages/creer', [
            'character' => [
                'name'              => $existingName,
                'gameClass'         => $gameClass->getId(),
                'server'            => $server->getId(),
                'level'             => 60,
                'optimizationLevel' => 'haute',
                '_token'            => $token,
            ],
        ]);

        // 422 attendu : Symfony retourne Unprocessable Content pour les form errors avec Turbo
        $this->assertStringContainsString('existe déjà', $this->client->getResponse()->getContent());
    }

    public function testNameSlotFreedOnOldServerIsReusableByAnotherUserAfterServerChange(): void
    {
        $serverA   = $this->makeServer();
        $serverB   = $this->makeServer();
        $gameClass = $this->makeGameClass();
        $mover     = $this->makeUser('mover@test.com');
        $char      = $this->makeCharacter($mover, $serverA);
        $charName  = $char->getName();
        $this->flush();

        // Le propriétaire change le personnage de serveur (serverA -> serverB) : couvert au
        // niveau HTTP par testEditChangingServerAsSimpleMemberLeavesGuildAndClearsParticipations.
        // Ici on déclenche directement le changement au niveau service pour ne garder qu'un seul
        // loginUser() dans ce test (deux loginUser() sur le même client ont déjà cassé un autre
        // test de la suite via une pollution du stockage de session/CSRF).
        $char->setServer($serverB);
        static::getContainer()->get(\App\Service\CharacterService::class)->clearParticipationsForServerChange($char);
        $this->flush();

        // Un autre utilisateur crée un personnage avec le même nom sur l'ancien serveur : doit réussir
        $other = $this->makeUser('other-namer@test.com');
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($other);
        $crawler = $this->client->request('GET', '/personnages/creer');
        $token   = $crawler->filter('input[name="character[_token]"]')->attr('value');

        $this->client->request('POST', '/personnages/creer', [
            'character' => [
                'name'              => $charName,
                'gameClass'         => $gameClass->getId(),
                'server'            => $serverA->getId(),
                'level'             => 60,
                'optimizationLevel' => 'haute',
                '_token'            => $token,
            ],
        ]);

        $this->assertResponseRedirects('/personnages');

        // Les deux personnages "test2" coexistent, chacun sur son propre serveur, ce sont des entités distinctes
        $charsWithName = $this->em->getRepository(\App\Entity\Character::class)->findBy(['name' => $charName]);
        $this->assertCount(2, $charsWithName);
        $serverIds = array_map(fn($c) => $c->getServer()->getId(), $charsWithName);
        $this->assertEqualsCanonicalizing([$serverA->getId(), $serverB->getId()], $serverIds);
    }

    public function testSameCharacterNameOnDifferentServerSucceeds(): void
    {
        $serverA   = $this->makeServer();
        $serverB   = $this->makeServer();
        $gameClass = $this->makeGameClass();
        $user      = $this->makeUser('diffserver@test.com');
        $char      = $this->makeCharacter($user, $serverA);
        $this->flush();
        $this->em->clear();

        $charName = $char->getName();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/personnages/creer');
        $token   = $crawler->filter('input[name="character[_token]"]')->attr('value');

        $this->client->request('POST', '/personnages/creer', [
            'character' => [
                'name'              => $charName,
                'gameClass'         => $gameClass->getId(),
                'server'            => $serverB->getId(),
                'level'             => 60,
                'optimizationLevel' => 'haute',
                '_token'            => $token,
            ],
        ]);

        $this->assertResponseRedirects('/personnages');
    }

    // ─── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testEditDeniedForOtherUsersCharacter(): void
    {
        $server  = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $char    = $this->makeCharacter($owner, $server);
        $other   = $this->makeUser('other@test.com');
        $this->flush();

        $this->client->loginUser($other);
        $this->client->request('GET', '/personnages/' . $char->getId() . '/modifier');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteDeniedForOtherUsersCharacter(): void
    {
        $server = $this->makeServer();
        $owner  = $this->makeUser('owner2@test.com');
        $char   = $this->makeCharacter($owner, $server);
        $other  = $this->makeUser('other2@test.com');
        $this->flush();

        $this->client->loginUser($other);
        $this->client->request('POST', '/personnages/' . $char->getId() . '/supprimer', [
            '_token' => 'bad-token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteLeaderCharacterShowsError(): void
    {
        $server    = $this->makeServer();
        $user      = $this->makeUser('leader@test.com');
        $char      = $this->makeCharacter($user, $server);
        $guild     = $this->makeGuild($user, $server);
        $this->makeMembership($guild, $char, MemberStatus::Leader);
        $this->flush();
        $charId = $char->getId();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/personnages');
        $token   = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/personnages/' . $charId . '/supprimer', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/personnages');
        $this->client->followRedirect();
        $this->assertStringContainsString('meneur', $this->client->getResponse()->getContent());
        $this->assertNotNull($this->em->find(\App\Entity\Character::class, $charId));
    }

    // ─── Modification du serveur ────────────────────────────────────────────────

    public function testEditChangingServerAsSimpleMemberLeavesGuildAndClearsParticipations(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $owner   = $this->makeUser('owner@test.com');
        $user    = $this->makeUser('member@test.com');
        $char    = $this->makeCharacter($user, $serverA);
        $guild   = $this->makeGuild($owner, $serverA);
        $this->makeMembership($guild, $char, MemberStatus::Member);
        $raid    = $this->makeRaid($guild, $this->makeCharacter($owner, $serverA));
        $participant = $this->makeParticipant($raid, $char, \App\Entity\RaidParticipantStatus::Accepted);
        $this->flush();
        $charId = $char->getId();
        $participantId = $participant->getId();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/personnages/' . $charId . '/modifier');
        $token   = $crawler->filter('input[name="character_edit[_token]"]')->attr('value');
        $char    = $this->em->find(\App\Entity\Character::class, $charId);

        $this->client->request('POST', '/personnages/' . $charId . '/modifier', [
            'character_edit' => [
                'name'              => $char->getName(),
                'gameClass'         => $char->getGameClass()->getId(),
                'server'            => $serverB->getId(),
                'level'             => $char->getLevel(),
                'optimizationLevel' => $char->getOptimizationLevel()->value,
                '_token'            => $token,
            ],
        ]);

        $this->assertResponseRedirects('/personnages');

        $this->em->clear();
        $reloaded = $this->em->find(\App\Entity\Character::class, $charId);
        $this->assertSame($serverB->getId(), $reloaded->getServer()->getId());
        $this->assertNull($reloaded->getMembership());
        $this->assertNull($this->em->find(\App\Entity\RaidParticipant::class, $participantId));
    }

    public function testEditChangingServerAsLeaderShowsErrorAndKeepsEverything(): void
    {
        $serverA = $this->makeServer();
        $serverB = $this->makeServer();
        $owner   = $this->makeUser('leaderowner@test.com');
        $char    = $this->makeCharacter($owner, $serverA);
        $guild   = $this->makeGuild($owner, $serverA);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Leader);
        $this->flush();
        $charId = $char->getId();
        $membershipId = $membership->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/personnages/' . $charId . '/modifier');
        $token   = $crawler->filter('input[name="character_edit[_token]"]')->attr('value');
        $char    = $this->em->find(\App\Entity\Character::class, $charId);

        $this->client->request('POST', '/personnages/' . $charId . '/modifier', [
            'character_edit' => [
                'name'              => $char->getName(),
                'gameClass'         => $char->getGameClass()->getId(),
                'server'            => $serverB->getId(),
                'level'             => $char->getLevel(),
                'optimizationLevel' => $char->getOptimizationLevel()->value,
                '_token'            => $token,
            ],
        ]);

        $this->assertStringContainsString('meneur', $this->client->getResponse()->getContent());

        $this->em->clear();
        $reloaded = $this->em->find(\App\Entity\Character::class, $charId);
        $this->assertSame($serverA->getId(), $reloaded->getServer()->getId());
        $this->assertNotNull($this->em->find(\App\Entity\GuildMembership::class, $membershipId));
    }

    public function testEditWithoutServerChangeKeepsGuildMembership(): void
    {
        $server = $this->makeServer();
        $owner  = $this->makeUser('staying-owner@test.com');
        $user   = $this->makeUser('staying@test.com');
        $char   = $this->makeCharacter($user, $server);
        $guild  = $this->makeGuild($owner, $server);
        $membership = $this->makeMembership($guild, $char, MemberStatus::Member);
        $this->flush();
        $charId = $char->getId();
        $membershipId = $membership->getId();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/personnages/' . $charId . '/modifier');
        $token   = $crawler->filter('input[name="character_edit[_token]"]')->attr('value');
        $char    = $this->em->find(\App\Entity\Character::class, $charId);

        $this->client->request('POST', '/personnages/' . $charId . '/modifier', [
            'character_edit' => [
                'name'              => $char->getName(),
                'gameClass'         => $char->getGameClass()->getId(),
                'server'            => $server->getId(),
                'level'             => 99,
                'optimizationLevel' => $char->getOptimizationLevel()->value,
                '_token'            => $token,
            ],
        ]);

        $this->assertResponseRedirects('/personnages');

        $this->em->clear();
        $this->assertNotNull($this->em->find(\App\Entity\GuildMembership::class, $membershipId));
        $this->assertSame(99, $this->em->find(\App\Entity\Character::class, $charId)->getLevel());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function charRepo(): \App\Repository\CharacterRepository
    {
        return static::getContainer()->get(\App\Repository\CharacterRepository::class);
    }
}
