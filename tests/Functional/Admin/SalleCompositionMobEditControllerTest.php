<?php

namespace App\Tests\Functional\Admin;

use App\Entity\SalleCompositionMob;
use App\Entity\User;
use App\Tests\Functional\WebTestCaseBase;

class SalleCompositionMobEditControllerTest extends WebTestCaseBase
{
    private function editUrl(int $compositionId): string
    {
        return '/admin/salle-composition/' . $compositionId . '/mobs';
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
        $salle = $this->makeSalle($template);
        $composition = $this->makeSalleComposition($salle);
        $this->flush();

        $this->client->request('GET', $this->editUrl($composition->getId()));

        $this->assertResponseRedirects();
    }

    public function testSubmittingCreatesUpdatesAndRemovesQuantities(): void
    {
        $admin = $this->makeAdmin();
        $template = $this->makeRaidTemplate('Gouffre du Gigalodon');
        $salle = $this->makeSalle($template);
        $composition = $this->makeSalleComposition($salle);

        $mobToUpdate = $this->makeMob($template, 'madrepire');
        $mobToRemove = $this->makeMob($template, 'krak-haine');
        $mobToCreate = $this->makeMob($template, 'kokayou');

        $this->makeSalleCompositionMob($composition, $mobToUpdate, 1); // sera mis à 3
        $this->makeSalleCompositionMob($composition, $mobToRemove, 2); // sera retiré (0)
        $this->flush();

        $compositionId = $composition->getId();
        $updateId = $mobToUpdate->getId();
        $removeId = $mobToRemove->getId();
        $createId = $mobToCreate->getId();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', $this->editUrl($compositionId));
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->editUrl($compositionId), [
            '_token' => $token,
            'mob_' . $updateId => '3',
            'mob_' . $removeId => '0',
            'mob_' . $createId => '2',
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $quantities = $this->em->getRepository(SalleCompositionMob::class)->findBy(['composition' => $compositionId]);
        $this->assertCount(2, $quantities);

        $byMobId = [];
        foreach ($quantities as $q) {
            $byMobId[$q->getMob()->getId()] = $q->getQuantity();
        }
        $this->assertSame(3, $byMobId[$updateId]);
        $this->assertArrayNotHasKey($removeId, $byMobId);
        $this->assertSame(2, $byMobId[$createId]);
    }
}
