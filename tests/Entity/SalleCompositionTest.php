<?php

namespace App\Tests\Entity;

use App\Entity\Gem;
use App\Entity\Mob;
use App\Entity\MobDropRate;
use App\Entity\Salle;
use App\Entity\SalleComposition;
use App\Entity\SalleCompositionMob;
use PHPUnit\Framework\TestCase;

class SalleCompositionTest extends TestCase
{
    private function makeMob(float $expectedPoints): Mob
    {
        $mob = new Mob();
        $gem = (new Gem())->setName('Onyx')->setValue(30);
        $mob->getDropRates()->add((new MobDropRate())->setMob($mob)->setGem($gem)->setProbability($expectedPoints / 30));
        return $mob;
    }

    // ─── getExpectedScore() ─────────────────────────────────────────────────────

    public function testGetExpectedScoreReturnsZeroWithoutMobQuantities(): void
    {
        $composition = new SalleComposition();
        $this->assertSame(0.0, $composition->getExpectedScore());
    }

    public function testGetExpectedScoreSumsQuantityTimesMobExpectedPoints(): void
    {
        $salle = (new Salle())->setName('Salle 1')->setLevelMin(10)->setLevelMax(14);
        $composition = (new SalleComposition())->setSalle($salle);

        $madrepire = $this->makeMob(2.78);
        $krakhaine = $this->makeMob(31.0);

        $composition->getMobQuantities()->add((new SalleCompositionMob())->setComposition($composition)->setMob($madrepire)->setQuantity(1));
        $composition->getMobQuantities()->add((new SalleCompositionMob())->setComposition($composition)->setMob($krakhaine)->setQuantity(2));

        // 1 madrepire (2,78) + 2 krak'haine (31) = 64,78 — cross-check PDF Salle 1 / composition 1
        $this->assertEqualsWithDelta(64.78, $composition->getExpectedScore(), 0.01);
    }
}
