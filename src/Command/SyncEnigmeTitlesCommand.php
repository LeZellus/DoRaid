<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-enigme-titles', description: 'Link legacy enigmes to their EnigmeTemplate (run once after deploy)')]
class SyncEnigmeTitlesCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->connection->executeStatement(
            'UPDATE enigme e
             JOIN raid r ON e.raid_id = r.id
             JOIN enigme_template et ON et.raid_template_id = r.raid_template_id
                                    AND et.order_number = e.order_number
             SET e.source_template_id = et.id
             WHERE e.source_template_id IS NULL'
        );

        $io->success(sprintf('%d legacy enigme(s) linked to their template.', $count));

        return Command::SUCCESS;
    }
}
