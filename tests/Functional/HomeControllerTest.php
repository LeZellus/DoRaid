<?php

namespace App\Tests\Functional;

class HomeControllerTest extends WebTestCaseBase
{
    public function testDashboardShowsAlertsForMissingInitiativeAndCauchemar(): void
    {
        $server = $this->makeServer();
        $user   = $this->makeUser('dashboard@test.com');
        $char   = $this->makeCharacter($user, $server);
        // guildatons/initiative/hasCauchemar restent null (jamais renseignés).
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'renseignez l\'initiative de');
        $this->assertSelectorTextContains('body', 'possède le Dofus Cauchemar');
        $this->assertSelectorTextContains('body', 'mettez à jour les guildatons de');
        $this->assertStringContainsString($char->getName(), $crawler->filter('body')->text());
    }

    public function testDashboardHidesAlertsWhenAllFieldsFilled(): void
    {
        $server = $this->makeServer();
        $user   = $this->makeUser('complete-dashboard@test.com');
        $char   = $this->makeCharacter($user, $server);
        $char->setGuildatons(100)->setGuildatonsUpdatedAt(new \DateTimeImmutable());
        $char->setInitiative(300);
        $char->setHasCauchemar(false);
        $this->flush();
        $this->em->clear();

        $this->client->loginUser($user);
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('renseignez l\'initiative de', $content);
        $this->assertStringNotContainsString('possède le Dofus Cauchemar', $content);
        $this->assertStringNotContainsString('mettez à jour les guildatons de', $content);
    }
}
