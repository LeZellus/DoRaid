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
    name: 'app:close-expired-raids',
    description: 'Cloture les raids dont la duree planifiee est depassee.',
)]
class CloseExpiredRaidsCommand extends Command
{
    public function __construct(
        private readonly RaidRepository $raidRepo,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now   = new \DateTimeImmutable();
        $count = 0;

        foreach ($this->raidRepo->findAllOpen() as $raid) {
            $end = $raid->getExpectedEndTime();
            if ($end !== null && $end <= $now) {
                $raid->setStatus(RaidStatus::Closed);
                $count++;
            }
        }

        if ($count > 0) {
            $this->em->flush();
        }

        $output->writeln(sprintf('<info>%d raid(s) clos.</info>', $count));

        return Command::SUCCESS;
    }
}
