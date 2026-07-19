<?php

namespace App\Tests\Functional\Admin;

use App\Entity\MobDropRate;
use App\Entity\User;
use App\Tests\Functional\WebTestCaseBase;

class MobDropRateEditControllerTest extends WebTestCaseBase
{
    private function editUrl(int $mobId): string
    {
        return '/admin/mob/' . $mobId . '/taux-de-drop';
    }

    private function makeAdmin(): User
    {
        $admin = $this->makeUser('admin@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        return $admin;
    }

    public function testAnonymousIsDeniedAccess(): void
    {
        $template = $this->makeRaidTemplate('Gouffre du Gigalodon');
        $mob = $this->makeMob($template, 'madrepire');
        $this->flush();

        $this->client->request('GET', $this->editUrl($mob->getId()));

        $this->assertResponseRedirects();
    }

    public function testShowsCurrentProbabilities(): void
    {
        $admin = $this->makeAdmin();
        $template = $this->makeRaidTemplate('Gouffre du Gigalodon');
        $mob = $this->makeMob($template, 'madrepire');
        $gem = $this->makeGem('Onyx', 30);
        $this->makeMobDropRate($mob, $gem, 0.30);
        $this->flush();
        $mobId = $mob->getId();
        $this->em->clear(); // force le rechargement de dropRates depuis la base (collection inverse)

        $this->client->loginUser($admin);
        $this->client->request('GET', $this->editUrl($mobId));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[value="30"]');
    }

    public function testSubmittingUpdatesExistingAndCreatesMissingRates(): void
    {
        $admin = $this->makeAdmin();
        $template = $this->makeRaidTemplate('Gouffre du Gigalodon');
        $mob = $this->makeMob($template, 'madrepire');
        $gem1 = $this->makeGem('Quartz', 2);
        $gem2 = $this->makeGem('Onyx', 30);
        $this->makeMobDropRate($mob, $gem1, 0.10); // sera mis à jour
        $this->flush();

        $mobId  = $mob->getId();
        $gem1Id = $gem1->getId();
        $gem2Id = $gem2->getId(); // n'a pas encore de MobDropRate

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', $this->editUrl($mobId));
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->editUrl($mobId), [
            '_token' => $token,
            'gem_' . $gem1Id => '50',
            'gem_' . $gem2Id => '5',
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $rates = $this->em->getRepository(MobDropRate::class)->findBy(['mob' => $mobId]);
        $this->assertCount(2, $rates);

        $byGemId = [];
        foreach ($rates as $rate) {
            $byGemId[$rate->getGem()->getId()] = $rate->getProbability();
        }
        $this->assertEqualsWithDelta(0.50, $byGemId[$gem1Id], 0.001);
        $this->assertEqualsWithDelta(0.05, $byGemId[$gem2Id], 0.001);
    }
}
