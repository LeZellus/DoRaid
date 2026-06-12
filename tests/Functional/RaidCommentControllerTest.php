<?php

namespace App\Tests\Functional;

use App\Entity\MemberStatus;
use App\Entity\RaidParticipantStatus;

class RaidCommentControllerTest extends WebTestCaseBase
{
    // ─── Anonyme ───────────────────────────────────────────────────────────────

    public function testAnonymousUserIsRedirectedToLoginOnComment(): void
    {
        $raidId = $this->createBaseRaid();

        $this->client->request('POST', '/raids/' . $raidId . '/commenter', [
            'content' => 'Test',
        ]);

        $this->assertResponseRedirects();
        $this->assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('location'));
    }

    // ─── Utilisateur non-postulant ─────────────────────────────────────────────

    public function testNonAppliedUserDoesNotSeeCommentForm(): void
    {
        $raidId = $this->createBaseRaid();
        $user   = $this->makeUser('lurker@test.com');
        $this->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('form[action$="commenter"]'));
    }

    public function testNonAppliedUserCannotPostComment(): void
    {
        $raidId = $this->createBaseRaid();
        $user   = $this->makeUser('lurker@test.com');
        $this->flush();

        $this->client->loginUser($user);
        // Envoi sans token CSRF valide → 403 (que ce soit CSRF ou hasApplied qui échoue)
        $this->client->request('POST', '/raids/' . $raidId . '/commenter', [
            '_token'  => 'invalid-token',
            'content' => 'Test',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ─── Utilisateur postulant ─────────────────────────────────────────────────

    public function testAppliedUserSeesCommentForm(): void
    {
        // Tout est créé en une passe, flush unique à la fin
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user = $this->makeUser('participant@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->makeParticipant($raid, $char, RaidParticipantStatus::Pending);

        $this->flush();
        $raidId = $raid->getId();
        // La collection participants du $raid en mémoire est initialisée à vide.
        // clear() force le rechargement depuis la BDD lors de la requête HTTP.
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[action$="commenter"]'));
    }

    public function testAppliedUserCanPostComment(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);

        $user = $this->makeUser('participant@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->makeParticipant($raid, $char, RaidParticipantStatus::Pending);

        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($user);

        // GET pour récupérer le token CSRF du formulaire de commentaire
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="commenter"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/' . $raidId . '/commenter', [
            '_token'  => $token,
            'content' => 'Super raid !',
        ]);

        $this->assertResponseStatusCodeSame(302);
        $this->assertStringContainsString(
            '/raids/' . $raidId,
            $this->client->getResponse()->headers->get('location'),
        );
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function createBaseRaid(): int
    {
        return $this->createBaseRaidExtended()[0];
    }

    /** Retourne [raidId, guild, server]. */
    private function createBaseRaidExtended(): array
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, \App\Entity\MemberStatus::Leader);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $this->flush();

        return [$raid->getId(), $guild, $server];
    }
}
