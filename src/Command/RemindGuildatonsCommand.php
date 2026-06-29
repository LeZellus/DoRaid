<?php

namespace App\Command;

use App\Repository\CharacterRepository;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:remind-guildatons',
    description: 'Envoie un rappel hebdomadaire aux joueurs qui n\'ont pas mis à jour leurs guildatons.',
)]
class RemindGuildatonsCommand extends Command
{
    public function __construct(
        private readonly CharacterRepository $characterRepo,
        private readonly NotificationRepository $notifRepo,
        private readonly NotificationService $notifService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $byUser = [];

        foreach ($this->characterRepo->findAll() as $character) {
            if (!$character->needsGuildatonsUpdate()) {
                continue;
            }

            $user = $character->getUser();
            $byUser[$user->getId()] ??= ['user' => $user, 'names' => []];
            $byUser[$user->getId()]['names'][] = $character->getName();
        }

        $sent = 0;
        foreach ($byUser as ['user' => $user, 'names' => $names]) {
            if ($this->notifRepo->hasRecentByType($user, 'guildatons_reminder', 7)) {
                continue;
            }

            $this->notifService->notifyGuildatonsReminder($user, $names);
            $sent++;
        }

        $this->em->flush();
        $output->writeln(sprintf('<info>%d rappel(s) envoyé(s).</info>', $sent));

        return Command::SUCCESS;
    }
}
