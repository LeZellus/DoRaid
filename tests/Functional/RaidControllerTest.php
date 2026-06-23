<?php

namespace App\Tests\Functional;

use App\Entity\Guild;
use App\Entity\MemberStatus;
use App\Entity\RaidStatus;
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

    // ─── Clôture automatique à l'affichage ─────────────────────────────────────

    public function testViewingExpiredRaidClosesItAutomatically(): void
    {
        [$raidId] = $this->createRaidWithGuild(isPublic: true);
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $raid->setScheduledAt(new \DateTimeImmutable('-2 hours')); // template par défaut : 60 min
        $this->flush();

        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->em->clear();
        $reloaded = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertSame(RaidStatus::Closed, $reloaded->getStatus());
    }

    public function testViewingStillOpenRaidDoesNotCloseIt(): void
    {
        [$raidId] = $this->createRaidWithGuild(isPublic: true);
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $raid->setScheduledAt(new \DateTimeImmutable('+10 minutes'));
        $this->flush();

        $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->em->clear();
        $reloaded = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertSame(RaidStatus::Open, $reloaded->getStatus());
    }

    public function testIndexClosesExpiredRaidAndExcludesItFromTheList(): void
    {
        [$raidId] = $this->createRaidWithGuild(isPublic: true);
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $raid->setScheduledAt(new \DateTimeImmutable('-2 hours'));
        $this->flush();

        $crawler = $this->client->request('GET', '/raids');

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $crawler->filter('a[href="/raids/' . $raidId . '"]')->count());
        $this->em->clear();
        $reloaded = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertSame(RaidStatus::Closed, $reloaded->getStatus());
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

    // ─── Double candidature ───────────────────────────────────────────────────

    public function testApplyTwiceWithSameCharacterShowsErrorNotException(): void
    {
        [$raidId, , , $server] = $this->createRaidWithGuild(isPublic: true);

        $user = $this->makeUser('twice@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush();
        $charId = $char->getId();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="candidater"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/' . $raidId . '/candidater', [
            '_token' => $token, 'character_id' => $charId,
        ]);
        $this->assertResponseStatusCodeSame(302);

        // Deuxième candidature avec le même token (non consommé par défaut en Symfony)
        $this->client->request('POST', '/raids/' . $raidId . '/candidater', [
            '_token' => $token, 'character_id' => $charId,
        ]);

        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->assertStringContainsString('déjà candidaté', $this->client->getResponse()->getContent());
    }

    // ─── Raid complet ─────────────────────────────────────────────────────────

    public function testApplyToFullRaidJoinsWaitlistInstead(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('raidowner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        // Template avec 1 participant max
        $template = (new \App\Entity\RaidTemplate())
            ->setName('Mini-' . uniqid('', true))
            ->setMaxParticipants(1)
            ->setMinParticipants(1)
            ->setDuration(60);
        $this->em->persist($template);

        $raid = (new \App\Entity\Raid())
            ->setGuild($guild)
            ->setCreator($ownerChar)
            ->setRaidTemplate($template)
            ->setIsPublic(true);
        $this->em->persist($raid);

        // Le créateur est déjà participant (ajouté à la création dans le controller)
        $this->em->persist(
            (new \App\Entity\RaidParticipant())
                ->setRaid($raid)
                ->setCharacter($ownerChar)
                ->setStatus(\App\Entity\RaidParticipantStatus::Accepted)
        );
        $this->flush();
        $raidId = $raid->getId();

        $applicant     = $this->makeUser('latecomer@test.com');
        $applicantChar = $this->makeCharacter($applicant, $server);
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($applicant);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="candidater"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/' . $raidId . '/candidater', [
            '_token' => $token, 'character_id' => $applicantChar->getId(),
        ]);

        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->assertStringContainsString('Candidature', $this->client->getResponse()->getContent());

        $this->em->clear();
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertCount(1, $raid->getPendingParticipants());
    }

    public function testAcceptingParticipantOnFullRaidShowsError(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('raidowner2@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        $template = (new \App\Entity\RaidTemplate())
            ->setName('Mini-' . uniqid('', true))
            ->setMaxParticipants(1)
            ->setMinParticipants(1)
            ->setDuration(60);
        $this->em->persist($template);

        $raid = (new \App\Entity\Raid())
            ->setGuild($guild)
            ->setCreator($ownerChar)
            ->setRaidTemplate($template)
            ->setIsPublic(true);
        $this->em->persist($raid);
        $this->em->persist(
            (new \App\Entity\RaidParticipant())
                ->setRaid($raid)
                ->setCharacter($ownerChar)
                ->setStatus(\App\Entity\RaidParticipantStatus::Accepted)
        );

        $waitlisted     = $this->makeUser('waitlisted2@test.com');
        $waitlistedChar = $this->makeCharacter($waitlisted, $server);
        $participant    = $this->makeParticipant($raid, $waitlistedChar, \App\Entity\RaidParticipantStatus::Pending);
        $this->flush();
        $raidId = $raid->getId();
        $participantId = $participant->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="/' . $participantId . '/accepter"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/participants/' . $participantId . '/accepter', [
            '_token' => $token,
        ]);

        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->assertStringContainsString('complet', $this->client->getResponse()->getContent());
    }

    // ─── Raid fermé ───────────────────────────────────────────────────────────

    public function testApplyToClosedRaidShowsError(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('closedowner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $raid = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $this->flush();
        $raidId = $raid->getId();

        $user = $this->makeUser('late@test.com');
        $char = $this->makeCharacter($user, $server);
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        // Récupère le token pendant que le raid est encore ouvert (form visible)
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="candidater"] input[name="_token"]')->attr('value');

        // Ferme le raid directement via l'EM (sans passer par le controller)
        $raidEntity = $this->em->find(\App\Entity\Raid::class, $raidId);
        $raidEntity->setStatus(\App\Entity\RaidStatus::Closed);
        $this->em->flush();

        $this->client->request('POST', '/raids/' . $raidId . '/candidater', [
            '_token' => $token, 'character_id' => $char->getId(),
        ]);

        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->assertStringContainsString('terminé', $this->client->getResponse()->getContent());
    }

    // ─── Contrôle d'accès ─────────────────────────────────────────────────────

    public function testNonMemberCannotCreateRaidAndIsRedirectedProperly(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('guildowner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $this->flush();
        $guildId   = $guild->getId();
        $guildSlug = $guild->getSlug();

        $nonMember = $this->makeUser('nobody@test.com');
        $this->flush();

        $this->client->loginUser($nonMember);
        $this->client->request('GET', '/raids/creer?guild=' . $guildId);

        // Ne doit pas 500 (ancien bug : route générée avec ['id'] au lieu de ['slug'])
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/guildes/' . $guildSlug, $this->client->getResponse()->headers->get('location'));
    }

    public function testMemberWithoutRaidPermissionCannotCreateRaid(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('rcperm-owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        $member     = $this->makeUser('rcperm-member@test.com');
        $memberChar = $this->makeCharacter($member, $server);
        $this->makeMembership($guild, $memberChar, MemberStatus::Member);
        $this->flush();
        $guildId   = $guild->getId();
        $guildSlug = $guild->getSlug();

        $this->client->loginUser($member);
        $this->client->request('GET', '/raids/creer?guild=' . $guildId);

        $this->assertResponseRedirects('/guildes/' . $guildSlug);
        $this->client->followRedirect();
        $this->assertStringContainsString('permission', $this->client->getResponse()->getContent());
    }

    public function testMemberWithExplicitPermissionCanCreateRaid(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('rcperm2-owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);

        $officer     = $this->makeUser('rcperm2-officer@test.com');
        $officerChar = $this->makeCharacter($officer, $server);
        $membership  = $this->makeMembership($guild, $officerChar, MemberStatus::Member);
        $membership->setCanCreateRaids(true);
        $template = $this->makeRaidTemplate('Permission-' . uniqid('', true));
        $this->flush();
        $guildId     = $guild->getId();
        $templateId  = $template->getId();
        $officerCharId = $officerChar->getId();
        $this->em->clear();

        $this->client->loginUser($officer);
        $crawler = $this->client->request('GET', '/raids/creer?guild=' . $guildId);
        $token   = $crawler->filter('input[name="raid[_token]"]')->attr('value');

        $this->client->request('POST', '/raids/creer?guild=' . $guildId, [
            'raid' => ['_token' => $token],
            'raid_template_id' => $templateId,
            'character_id'      => $officerCharId,
        ]);

        $this->assertResponseStatusCodeSame(302);
        $location = $this->client->getResponse()->headers->get('location');
        $this->assertStringContainsString('/raids/', $location);
        $this->assertStringNotContainsString('/guildes/', $location);
    }

    public function testNonCreatorCannotCloseRaid(): void
    {
        [$raidId] = $this->createRaidWithGuild(isPublic: true);

        $other = $this->makeUser('notthecreator@test.com');
        $this->flush();

        $this->client->loginUser($other);
        // Token invalide suffit : le contrôleur retourne 403 dès le check CSRF (createAccessDeniedException)
        $this->client->request('POST', '/raids/' . $raidId . '/clore', ['_token' => 'invalid']);

        $this->assertResponseStatusCodeSame(403);
    }

    // ─── Actions créateur (close / delete / accept / kick) ────────────────────

    public function testCreatorCanCloseRaid(): void
    {
        [$raidId, , $guild, $server] = $this->createRaidWithGuild(isPublic: true);

        $owner = $guild->getOwner();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="clore"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/' . $raidId . '/clore', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(302);
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertSame(\App\Entity\RaidStatus::Closed, $raid->getStatus());
    }

    public function testCreatorCanDeleteRaid(): void
    {
        [$raidId, $guildSlug, $guild] = $this->createRaidWithGuild(isPublic: true);

        $owner = $guild->getOwner();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="supprimer"]:not([action*="commentaires"]) input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/' . $raidId . '/supprimer', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(302);
        $this->assertStringContainsString('/guildes/' . $guildSlug, $this->client->getResponse()->headers->get('location'));
        $this->assertNull($this->em->find(\App\Entity\Raid::class, $raidId));
    }

    public function testCreatorCanAcceptPendingParticipant(): void
    {
        [$raidId, , $guild, $server] = $this->createRaidWithGuild(isPublic: true);

        $user        = $this->makeUser('pending@test.com');
        $char        = $this->makeCharacter($user, $server);
        $raid        = $this->em->find(\App\Entity\Raid::class, $raidId);
        $participant = $this->makeParticipant($raid, $char, \App\Entity\RaidParticipantStatus::Pending);
        $this->flush();
        $participantId = $participant->getId();
        $this->em->clear();

        $owner = $guild->getOwner();
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="accepter"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/participants/' . $participantId . '/accepter', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(302);
        $reloaded = $this->em->find(\App\Entity\RaidParticipant::class, $participantId);
        $this->assertSame(\App\Entity\RaidParticipantStatus::Accepted, $reloaded->getStatus());
    }

    public function testCreatorCanKickParticipant(): void
    {
        [$raidId, , $guild, $server] = $this->createRaidWithGuild(isPublic: true);

        $user        = $this->makeUser('tokick@test.com');
        $char        = $this->makeCharacter($user, $server);
        $raid        = $this->em->find(\App\Entity\Raid::class, $raidId);
        $participant = $this->makeParticipant($raid, $char, \App\Entity\RaidParticipantStatus::Accepted);
        $this->flush();
        $participantId = $participant->getId();
        $this->em->clear();

        $owner = $guild->getOwner();
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $crawler->filter('form[action$="exclure"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/raids/participants/' . $participantId . '/exclure', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(302);
        $this->assertNull($this->em->find(\App\Entity\RaidParticipant::class, $participantId));
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
