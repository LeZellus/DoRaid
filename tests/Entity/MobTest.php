<?php

namespace App\Tests\Entity;

use App\Entity\Gem;
use App\Entity\Mob;
use App\Entity\MobDropRate;
use PHPUnit\Framework\TestCase;

class MobTest extends TestCase
{
    // ─── getExpectedPoints() ────────────────────────────────────────────────────

    public function testGetExpectedPointsReturnsZeroWithoutDropRates(): void
    {
        $mob = new Mob();
        $this->assertSame(0.0, $mob->getExpectedPoints());
    }

    public function testGetExpectedPointsMatchesPdfForMadrepire(): void
    {
        $mob = new Mob();
        $rates = [
            ['Quartz', 2, 0.30], ['Opale', 4, 0.20], ['Amazonite', 6, 0.10],
            ['Aventurine', 10, 0.05], ['Lapiz', 15, 0.01], ['Jais', 20, 0.005], ['Onyx', 30, 0.001],
        ];
        foreach ($rates as [$name, $value, $probability]) {
            $gem = (new Gem())->setName($name)->setValue($value);
            $mob->getDropRates()->add((new MobDropRate())->setMob($mob)->setGem($gem)->setProbability($probability));
        }

        $this->assertEqualsWithDelta(2.78, $mob->getExpectedPoints(), 0.01);
    }
}
