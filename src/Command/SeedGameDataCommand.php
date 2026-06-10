<?php

namespace App\Command;

use App\Entity\GameClass;
use App\Entity\RaidTemplate;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:game-data', description: 'Insère les classes et serveurs Dofus en base.')]
class SeedGameDataCommand extends Command
{
    private const CLASSES = [
        'Cra', 'Ecaflip', 'Eniripsa', 'Enutrof', 'Feca', 'Iop',
        'Osamodas', 'Pandawa', 'Roublard', 'Sacrieur', 'Sadida',
        'Sram', 'Xelor', 'Zobal', 'Steamer', 'Eliotrope', 'Huppermage', 'Ouginak',
    ];

    private const RAID_TEMPLATES = [
        ['name' => 'Belladone',  'min' => 8,  'max' => 12],
        ['name' => 'Gigalodon', 'min' => 12, 'max' => 16],
    ];

    private const SERVERS = [
        // Mono-compte
        'Draconiros', 'Hell Mina', 'Imagiro', 'Ilyzaelle', 'Jahash',
        // Multi-compte
        'Tylezia', 'Orukam', 'Ombre', 'Agride', 'Crail',
        // Rétro
        'Boune', 'Fallanster', 'Galgarion', 'Kourial',
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $classRepo = $this->em->getRepository(GameClass::class);
        $added = 0;
        foreach (self::CLASSES as $name) {
            if (!$classRepo->findOneBy(['name' => $name])) {
                $class = (new GameClass())->setName($name);
                $this->em->persist($class);
                $added++;
            }
        }

        $serverRepo = $this->em->getRepository(Server::class);
        foreach (self::SERVERS as $name) {
            if (!$serverRepo->findOneBy(['name' => $name])) {
                $server = (new Server())->setName($name);
                $this->em->persist($server);
                $added++;
            }
        }

        $templateRepo = $this->em->getRepository(RaidTemplate::class);
        foreach (self::RAID_TEMPLATES as ['name' => $name, 'min' => $min, 'max' => $max]) {
            if (!$templateRepo->findOneBy(['name' => $name])) {
                $t = (new RaidTemplate())->setName($name)->setMinParticipants($min)->setMaxParticipants($max);
                $this->em->persist($t);
                $added++;
            }
        }

        $this->em->flush();
        $io->success("$added entrée(s) insérée(s).");

        return Command::SUCCESS;
    }
}
