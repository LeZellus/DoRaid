<?php

namespace App\Command;

use App\Repository\RaidRepository;
use App\Service\RaidService;
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
        private readonly RaidService $raidService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;

        foreach ($this->raidRepo->findAllOpen() as $raid) {
            if ($this->raidService->closeIfExpired($raid)) {
                $count++;
            }
        }

        $output->writeln(sprintf('<info>%d raid(s) clos.</info>', $count));

        return Command::SUCCESS;
    }
}
