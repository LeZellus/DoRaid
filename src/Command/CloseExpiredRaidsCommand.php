<?php

namespace App\Command;

use App\Entity\RaidStatus;
use App\Repository\RaidRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:raids:auto-close',
    description: 'Close raids that have exceeded their scheduled duration',
)]
class CloseExpiredRaidsCommand extends Command
{
    public function __construct(
        private RaidRepository $raidRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now   = new \DateTimeImmutable();
        $raids = $this->raidRepo->findBy(['status' => RaidStatus::Open]);
        $count = 0;

        foreach ($raids as $raid) {
            $end = $raid->getExpectedEndTime();
            if ($end !== null && $end <= $now) {
                $raid->setStatus(RaidStatus::Closed);
                $count++;
            }
        }

        if ($count > 0) {
            $this->em->flush();
        }

        $output->writeln(sprintf('%d raid(s) fermé(s).', $count));
        return Command::SUCCESS;
    }
}
