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
