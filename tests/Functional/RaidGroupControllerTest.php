<?php

namespace App\Tests\Functional;

use App\Entity\MemberStatus;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use Symfony\Component\DomCrawler\Crawler;

class RaidGroupControllerTest extends WebTestCaseBase
{
    // ─── Création de groupe ─────────────────────────────────────────────────────

    public function testCreatorCanCreateGroup(): void
    {
        [$raid, $owner] = $this->createOpenRaidWithCreator();
        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/groupes/creer');

        $this->client->request('POST', '/raids/' . $raidId . '/groupes/creer', ['_token' => $token]);

        $this->assertResponseRedirects('/raids/' . $raidId . '#groupes');
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertCount(1, $raid->getGroups());
    }

    public function testCreateGroupWithTurboStreamHeaderReturnsStreamResponse(): void
    {
        [$raid, $owner] = $this->createOpenRaidWithCreator();
        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/groupes/creer');

        $this->client->request(
            'POST',
            '/raids/' . $raidId . '/groupes/creer',
            ['_token' => $token],
            [],
            ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']
        );

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/vnd.turbo-stream.html', $this->client->getResponse()->headers->get('Content-Type'));
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('targets="#raid-groups-frame"', $content);
        $this->assertStringContainsString('targets="#raid-groups-band-count"', $content);
        $this->assertStringContainsString('Groupe créé.', $content);
        // Le groupe fraîchement créé doit apparaître dans la réponse elle-même (pas seulement
        // en base) : sinon la popup resterait vide jusqu'au prochain rechargement.
        $this->assertStringContainsString('Groupe 1', $content);
        $this->assertStringContainsString('1 groupe formé', $content);

        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertCount(1, $raid->getGroups());
    }

    public function testNonCreatorCannotCreateGroup(): void
    {
        [$raid] = $this->createOpenRaidWithCreator();
        $stranger = $this->makeUser('stranger@test.com');
        $this->flush();
        $raidId = $raid->getId();

        $this->client->loginUser($stranger);
        $this->client->request('POST', '/raids/' . $raidId . '/groupes/creer', ['_token' => 'invalid']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCannotCreateGroupOnClosedRaid(): void
    {
        [$raid, $owner] = $this->createOpenRaidWithCreator();
        $this->flush();
        $raidId = $raid->getId();

        $this->client->loginUser($owner);
        // Token récupéré pendant que le raid est encore ouvert (formulaire visible) ;
        // la validité CSRF ne dépend pas de l'état métier, seulement de la session.
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/groupes/creer');

        $raid->setStatus(RaidStatus::Closed);
        $this->flush();

        $this->client->request('POST', '/raids/' . $raidId . '/groupes/creer', ['_token' => $token]);

        $this->assertResponseRedirects('/raids/' . $raidId . '#groupes');
        $this->client->followRedirect();
        $this->assertStringContainsString('terminé', $this->client->getResponse()->getContent());

        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertCount(0, $raid->getGroups());
    }

    // ─── Renommage ──────────────────────────────────────────────────────────────

    public function testCreatorCanRenameGroup(): void
    {
        [$raid, $owner] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, null, 1);
        $this->flush();
        $raidId  = $raid->getId();
        $groupId = $group->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/groupes/' . $groupId . '/renommer');

        $this->client->request('POST', '/raids/groupes/' . $groupId . '/renommer', [
            '_token' => $token,
            'label'  => 'Groupe DPS',
        ]);

        $this->assertResponseRedirects();
        $this->em->clear();
        $group = $this->em->find(\App\Entity\RaidGroup::class, $groupId);
        $this->assertSame('Groupe DPS', $group->getLabel());
    }

    public function testRenameGroupRejectsLabelOverFiftyCharacters(): void
    {
        [$raid] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, null, 1);
        $this->flush();

        $this->expectException(\App\Exception\BusinessRuleException::class);
        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->renameGroup($group, str_repeat('x', 51));
    }

    // ─── Assignation ────────────────────────────────────────────────────────────

    public function testCreatorCanAssignAcceptedParticipantToGroup(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, null, 1);

        $user = $this->makeUser('member@test.com');
        $char = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();
        $participantId = $participant->getId();
        $groupId       = $group->getId();
        $raidId        = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/participants/' . $participantId . '/assigner');

        $this->client->request('POST', '/raids/participants/' . $participantId . '/assigner', [
            '_token'   => $token,
            'group_id' => $groupId,
        ]);

        $this->assertResponseRedirects('/raids/' . $raidId . '#groupes');
        $this->em->clear();
        $participant = $this->em->find(\App\Entity\RaidParticipant::class, $participantId);
        $this->assertSame($groupId, $participant->getGroup()->getId());
    }

    public function testAssignWithTurboStreamShowsCorrectSuccessMessage(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, 'Groupe DPS', 1);

        $user = $this->makeUser('member4@test.com');
        $char = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();
        $participantId = $participant->getId();
        $groupId       = $group->getId();
        $raidId        = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/participants/' . $participantId . '/assigner');

        $this->client->request(
            'POST',
            '/raids/participants/' . $participantId . '/assigner',
            ['_token' => $token, 'group_id' => $groupId],
            [],
            ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']
        );

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString($char->getName() . ' a été assigné à Groupe DPS.', $content);
        // Le membre doit apparaître DANS la carte du groupe (compteur à jour), pas seulement
        // dans le message de succès — sinon la popup resterait visuellement obsolète.
        $this->assertStringContainsString('1/8', $content);
    }

    public function testCannotAssignPendingParticipant(): void
    {
        // Un participant en attente n'a pas de formulaire d'assignation dans la page
        // (garde déjà assurée par l'UI) ; on vérifie ici la garde métier elle-même,
        // au niveau du service, en cas d'appel direct à la route.
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, null, 1);

        $user = $this->makeUser('pending@test.com');
        $char = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Pending);
        $this->flush();

        $this->expectException(\App\Exception\BusinessRuleException::class);
        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->assignParticipant($participant, $group);
    }

    public function testFullGroupIsNotOfferedInAssignMenu(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, 'Groupe complet', 1);

        for ($i = 0; $i < 8; $i++) {
            $u = $this->makeUser("full{$i}@test.com");
            $c = $this->makeCharacter($u, $server);
            $this->makeParticipant($raid, $c, RaidParticipantStatus::Accepted)->setGroup($group);
        }
        $ninthUser = $this->makeUser('ninth@test.com');
        $ninthChar = $this->makeCharacter($ninthUser, $server);
        $ninth     = $this->makeParticipant($raid, $ninthChar, RaidParticipantStatus::Accepted);
        $this->flush();
        $ninthId = $ninth->getId();
        $raidId  = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        // Le groupe complet ne doit plus jamais apparaître comme cible sélectionnable
        // dans le menu "Assigner" du neuvième participant (correctif du 2026-07-06).
        $this->assertCount(
            0,
            $crawler->filter('form[action$="/participants/' . $ninthId . '/assigner"] input[name="group_id"][value="' . $group->getId() . '"]')
        );
    }

    public function testAssignParticipantRejectsFullGroup(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, null, 1);

        for ($i = 0; $i < 8; $i++) {
            $u = $this->makeUser("full{$i}@test.com");
            $c = $this->makeCharacter($u, $server);
            $this->makeParticipant($raid, $c, RaidParticipantStatus::Accepted)->setGroup($group);
        }
        $ninthUser = $this->makeUser('ninth@test.com');
        $ninthChar = $this->makeCharacter($ninthUser, $server);
        $ninth     = $this->makeParticipant($raid, $ninthChar, RaidParticipantStatus::Accepted);
        $this->flush();
        $ninthId = $ninth->getId();
        $groupId = $group->getId();
        // Sans ce clear(), $group->getParticipants() resterait la simple ArrayCollection
        // vide du constructeur (jamais synchronisée côté inverse par setGroup()) : il faut
        // un re-fetch propre pour obtenir une collection reflétant vraiment les 8 membres.
        $this->em->clear();
        $ninth = $this->em->find(\App\Entity\RaidParticipant::class, $ninthId);
        $group = $this->em->find(\App\Entity\RaidGroup::class, $groupId);

        // Garde métier vérifiée directement (au cas où l'action serait déclenchée
        // autrement qu'via le menu, qui filtre déjà les groupes pleins côté UI).
        $this->expectException(\App\Exception\BusinessRuleException::class);
        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->assignParticipant($ninth, $group);
    }

    // ─── Suppression ────────────────────────────────────────────────────────────

    public function testDeletingGroupUnassignsMembers(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, null, 1);

        $user = $this->makeUser('member2@test.com');
        $char = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $participant->setGroup($group);
        $this->flush();
        $participantId = $participant->getId();
        $groupId       = $group->getId();
        $raidId        = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/groupes/' . $groupId . '/supprimer');

        $this->client->request('POST', '/raids/groupes/' . $groupId . '/supprimer', ['_token' => $token]);

        $this->assertResponseRedirects();
        $this->em->clear();
        $this->assertNull($this->em->find(\App\Entity\RaidGroup::class, $groupId));
        $participant = $this->em->find(\App\Entity\RaidParticipant::class, $participantId);
        $this->assertNull($participant->getGroup());
    }

    // ─── Affichage ──────────────────────────────────────────────────────────────

    public function testShowPageDisplaysGroupsCard(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, 'Groupe Tank', 1);

        $user = $this->makeUser('viewer@test.com');
        $char = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $participant->setGroup($group);
        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Groupe Tank');
        $this->assertSelectorTextContains('body', $char->getName());
    }

    public function testFinalGroupShowsCauchemarStatus(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();

        $hasUser = $this->makeUser('hascauchemar@test.com');
        $hasChar = $this->makeCharacter($hasUser, $server);
        $hasChar->setHasCauchemar(true);
        $this->makeParticipant($raid, $hasChar, RaidParticipantStatus::Accepted);

        $noUser = $this->makeUser('nocauchemar@test.com');
        $noChar = $this->makeCharacter($noUser, $server);
        $noChar->setHasCauchemar(false);
        $this->makeParticipant($raid, $noChar, RaidParticipantStatus::Accepted);

        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $hasImg = $crawler->filter('img[alt="Dofus Cauchemar obtenu"]');
        $noImg  = $crawler->filter('img[alt="Dofus Cauchemar non obtenu"]');
        $this->assertGreaterThanOrEqual(1, $hasImg->count());
        $this->assertGreaterThanOrEqual(1, $noImg->count());
        $this->assertStringNotContainsString('grayscale', $hasImg->first()->attr('class'));
        $this->assertStringContainsString('grayscale', $noImg->first()->attr('class'));
    }

    public function testGroupsRemainManageableWhenNoAcceptedParticipants(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner2@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        // Aucun participant accepté (contrairement à createOpenRaidWithCreator) : le groupe
        // doit rester visible et gérable malgré tout (correctif du 2026-07-06).
        $group = $this->makeRaidGroup($raid, 'Groupe fantôme', 1);
        $this->flush();
        $raidId = $raid->getId();
        $groupId = $group->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Groupe fantôme');
        $this->assertCount(1, $crawler->filter('form[action$="/groupes/' . $groupId . '/supprimer"]'));
    }

    public function testNonCreatorSeesGroupsReadOnly(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $group = $this->makeRaidGroup($raid, 'Groupe Tank', 1);

        $viewer = $this->makeUser('member3@test.com');
        $char   = $this->makeCharacter($viewer, $server);
        $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $this->client->loginUser($viewer);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Groupe Tank');
        $this->assertCount(0, $crawler->filter('form[action$="/groupes/creer"]'));
    }

    public function testFinalGroupIsSortedByInitiativeWithNullsLast(): void
    {
        // Le créateur (participant accepté par défaut) n'a pas d'initiative renseignée (null par défaut).
        [$raid, , $server] = $this->createOpenRaidWithCreator();

        $low = $this->makeUser('low@test.com');
        $lowChar = $this->makeCharacter($low, $server)->setInitiative(50);
        $this->makeParticipant($raid, $lowChar, RaidParticipantStatus::Accepted);

        $high = $this->makeUser('high@test.com');
        $highChar = $this->makeCharacter($high, $server)->setInitiative(200);
        $this->makeParticipant($raid, $highChar, RaidParticipantStatus::Accepted);

        $this->flush();
        $raidId = $raid->getId();
        $this->em->clear();

        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $ordered = $raid->getParticipantsByInitiative();

        $this->assertSame($highChar->getName(), $ordered[0]->getCharacter()->getName());
        $this->assertSame($lowChar->getName(), $ordered[1]->getCharacter()->getName());
        $this->assertNull($ordered[2]->getCharacter()->getInitiative());
    }

    // ─── Modificateurs d'initiative (Gigalodon) ────────────────────────────────

    public function testCreatorCanToggleInitiativeModifier(): void
    {
        [$raid, $owner, $server] = $this->createOpenRaidWithCreator();
        $user = $this->makeUser('brandade@test.com');
        $char = $this->makeCharacter($user, $server)->setInitiative(300);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();
        $raidId        = $raid->getId();
        $participantId = $participant->getId();
        $this->em->clear();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/raids/' . $raidId);
        $token   = $this->tokenFor($crawler, '/participants/' . $participantId . '/moduler-initiative');

        $this->client->request('POST', '/raids/participants/' . $participantId . '/moduler-initiative', [
            '_token'   => $token,
            'modifier' => \App\Entity\InitiativeModifier::Exaltante->value,
        ]);

        $this->assertResponseRedirects('/raids/' . $raidId . '#groupes');
        $this->em->clear();
        $participant = $this->em->find(\App\Entity\RaidParticipant::class, $participantId);
        $this->assertTrue($participant->hasInitiativeModifier(\App\Entity\InitiativeModifier::Exaltante));
        $this->assertSame(500, $participant->getInitiativeModifierTotal());
        $this->assertSame(800, $participant->getEffectiveInitiative());
        // Le personnage lui-même ne doit jamais être modifié — seul le raid l'est.
        $this->assertSame(300, $participant->getCharacter()->getInitiative());
    }

    public function testTogglingSameModifierTwiceRemovesIt(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $user = $this->makeUser('toggle@test.com');
        $char = $this->makeCharacter($user, $server)->setInitiative(300);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->toggleInitiativeModifier($participant, \App\Entity\InitiativeModifier::Cauchemar);
        $this->assertTrue($participant->hasInitiativeModifier(\App\Entity\InitiativeModifier::Cauchemar));

        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->toggleInitiativeModifier($participant, \App\Entity\InitiativeModifier::Cauchemar);
        $this->assertFalse($participant->hasInitiativeModifier(\App\Entity\InitiativeModifier::Cauchemar));
        $this->assertSame(0, $participant->getInitiativeModifierTotal());
        $this->assertSame(300, $participant->getEffectiveInitiative());
    }

    public function testInitiativeModifiersAreCumulative(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $user = $this->makeUser('cumul@test.com');
        $char = $this->makeCharacter($user, $server)->setInitiative(100);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        $service = static::getContainer()->get(\App\Service\RaidGroupService::class);
        $service->toggleInitiativeModifier($participant, \App\Entity\InitiativeModifier::Epuisante);
        $service->toggleInitiativeModifier($participant, \App\Entity\InitiativeModifier::Cauchemar);

        // -500 + 1000 = +500, cumulés sur les deux éléments actifs.
        $this->assertSame(500, $participant->getInitiativeModifierTotal());
        $this->assertSame(600, $participant->getEffectiveInitiative());
    }

    public function testEffectiveInitiativeWithoutBaseButWithModifier(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $user = $this->makeUser('nobase@test.com');
        $char = $this->makeCharacter($user, $server); // initiative de base non renseignée (null)
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $this->flush();

        $this->assertNull($participant->getEffectiveInitiative());

        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->toggleInitiativeModifier($participant, \App\Entity\InitiativeModifier::Energisante);

        $this->assertSame(200, $participant->getEffectiveInitiative());
    }

    public function testNonCreatorCannotToggleInitiativeModifier(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();
        $user = $this->makeUser('victim@test.com');
        $char = $this->makeCharacter($user, $server);
        $participant = $this->makeParticipant($raid, $char, RaidParticipantStatus::Accepted);
        $stranger = $this->makeUser('stranger2@test.com');
        $this->flush();
        $participantId = $participant->getId();

        $this->client->loginUser($stranger);
        $this->client->request('POST', '/raids/participants/' . $participantId . '/moduler-initiative', [
            '_token'   => 'invalid',
            'modifier' => \App\Entity\InitiativeModifier::Stimulante->value,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testInitiativeModifierChangesFinalGroupOrder(): void
    {
        [$raid, , $server] = $this->createOpenRaidWithCreator();

        $lowUser = $this->makeUser('lowmod@test.com');
        $lowChar = $this->makeCharacter($lowUser, $server)->setInitiative(100);
        $lowParticipant = $this->makeParticipant($raid, $lowChar, RaidParticipantStatus::Accepted);

        $highUser = $this->makeUser('highmod@test.com');
        $highChar = $this->makeCharacter($highUser, $server)->setInitiative(900);
        $this->makeParticipant($raid, $highChar, RaidParticipantStatus::Accepted);

        $this->flush();

        // Le plus faible en base (100) devient premier grâce au Dofus Cauchemar (+1000 = 1100).
        static::getContainer()->get(\App\Service\RaidGroupService::class)
            ->toggleInitiativeModifier($lowParticipant, \App\Entity\InitiativeModifier::Cauchemar);

        $raidId = $raid->getId();
        $this->em->clear();
        $raid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $ordered = $raid->getParticipantsByInitiative();

        $this->assertSame($lowChar->getName(), $ordered[0]->getCharacter()->getName());
        $this->assertSame(1100, $ordered[0]->getEffectiveInitiative());
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** @return array{0: \App\Entity\Raid, 1: \App\Entity\User, 2: \App\Entity\Server} */
    private function createOpenRaidWithCreator(): array
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $this->makeMembership($guild, $ownerChar, MemberStatus::Leader);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $this->makeParticipant($raid, $ownerChar, RaidParticipantStatus::Accepted);

        return [$raid, $owner, $server];
    }

    private function tokenFor(Crawler $crawler, string $actionSuffix): string
    {
        return $crawler->filter('form[action$="' . $actionSuffix . '"] input[name="_token"]')->attr('value');
    }
}
