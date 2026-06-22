<?php

namespace App\Tests\Command;

use App\Command\CloseExpiredRaidsCommand;
use App\Entity\Raid;
use App\Entity\RaidStatus;
use App\Tests\Functional\WebTestCaseBase;
use Symfony\Component\Console\Tester\CommandTester;

class CloseExpiredRaidsCommandTest extends WebTestCaseBase
{
    private function tester(): CommandTester
    {
        return new CommandTester(static::getContainer()->get(CloseExpiredRaidsCommand::class));
    }

    public function testClosesOnlyExpiredOpenRaids(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);

        $expired = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $expired->setScheduledAt(new \DateTimeImmutable('-2 hours')); // template par défaut : 60 min

        $stillOpen = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $stillOpen->setScheduledAt(new \DateTimeImmutable('+30 minutes'));

        $ongoing = $this->makeRaid($guild, $ownerChar, isPublic: true);
        // scheduledAt null : raid "ongoing", jamais clôturé automatiquement

        $this->flush();
        [$expiredId, $stillOpenId, $ongoingId] = [$expired->getId(), $stillOpen->getId(), $ongoing->getId()];

        $exitCode = $this->tester()->execute([]);

        $this->assertSame(0, $exitCode);
        $this->em->clear();
        $this->assertSame(RaidStatus::Closed, $this->em->find(Raid::class, $expiredId)->getStatus());
        $this->assertSame(RaidStatus::Open, $this->em->find(Raid::class, $stillOpenId)->getStatus());
        $this->assertSame(RaidStatus::Open, $this->em->find(Raid::class, $ongoingId)->getStatus());
    }

    public function testReportsTheNumberOfClosedRaids(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);

        $raid1 = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid1->setScheduledAt(new \DateTimeImmutable('-2 hours'));
        $raid2 = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid2->setScheduledAt(new \DateTimeImmutable('-3 hours'));
        $this->flush();

        $tester = $this->tester();
        $tester->execute([]);

        $this->assertStringContainsString('2 raid(s) clos.', $tester->getDisplay());
    }

    public function testDoesNothingWhenNoRaidIsExpired(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar, isPublic: true);
        $raid->setScheduledAt(new \DateTimeImmutable('+1 hour'));
        $this->flush();
        $raidId = $raid->getId();

        $tester = $this->tester();
        $tester->execute([]);

        $this->assertStringContainsString('0 raid(s) clos.', $tester->getDisplay());
        $this->em->clear();
        $this->assertSame(RaidStatus::Open, $this->em->find(Raid::class, $raidId)->getStatus());
    }
}
