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

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function charRepo(): \App\Repository\CharacterRepository
    {
        return static::getContainer()->get(\App\Repository\CharacterRepository::class);
    }
}
