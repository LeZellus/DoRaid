<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-enigme-titles', description: 'Sync enigme titles from their EnigmeTemplate')]
class SyncEnigmeTitlesCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Show current state
        $rows = $this->connection->fetchAllAssociative(
            'SELECT e.id, e.title AS enigme_title, et.title AS template_title, e.order_number, e.source_template_id
             FROM enigme e
             JOIN raid r ON e.raid_id = r.id
             JOIN enigme_template et ON et.raid_template_id = r.raid_template_id AND et.order_number = e.order_number
             WHERE e.title != et.title'
        );

        if (empty($rows)) {
            $io->success('All enigme titles are already in sync.');
            return Command::SUCCESS;
        }

        $io->table(
            ['enigme id', 'current title', 'template title'],
            array_map(fn($r) => [$r['id'], $r['enigme_title'], $r['template_title']], $rows)
        );

        $count = $this->connection->executeStatement(
            'UPDATE enigme e
             JOIN raid r ON e.raid_id = r.id
             JOIN enigme_template et ON et.raid_template_id = r.raid_template_id AND et.order_number = e.order_number
             SET e.title = et.title,
                 e.source_template_id = et.id
             WHERE e.title != et.title'
        );

        $io->success(sprintf('%d enigme(s) updated.', $count));

        return Command::SUCCESS;
    }
}
