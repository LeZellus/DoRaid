<?php

namespace App\Tests\Entity;

use App\Entity\RaidTemplate;
use PHPUnit\Framework\TestCase;

class RaidTemplateTest extends TestCase
{
    // ─── getFormattedDuration() ────────────────────────────────────────────────

    public function testGetFormattedDurationReturnsNullWhenNoDuration(): void
    {
        $template = new RaidTemplate();
        $this->assertNull($template->getFormattedDuration());
    }

    public function testGetFormattedDurationShowsMinutesOnly(): void
    {
        $template = (new RaidTemplate())->setDuration(45);
        $this->assertSame('45min', $template->getFormattedDuration());
    }

    public function testGetFormattedDurationShowsExactHour(): void
    {
        $template = (new RaidTemplate())->setDuration(60);
        $this->assertSame('1h', $template->getFormattedDuration());
    }

    public function testGetFormattedDurationShowsHoursAndMinutes(): void
    {
        $template = (new RaidTemplate())->setDuration(90);
        $this->assertSame('1h30', $template->getFormattedDuration());
    }

    public function testGetFormattedDurationPadsMinutesBelowTen(): void
    {
        $template = (new RaidTemplate())->setDuration(125);
        $this->assertSame('2h05', $template->getFormattedDuration());
    }

    public function testGetFormattedDurationMultipleHours(): void
    {
        $template = (new RaidTemplate())->setDuration(180);
        $this->assertSame('3h', $template->getFormattedDuration());
    }
}
