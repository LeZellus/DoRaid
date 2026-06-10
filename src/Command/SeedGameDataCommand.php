<?php

namespace App\Command;

use App\Entity\EnigmeTemplate;
use App\Entity\GameClass;
use App\Entity\RaidTemplate;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:game-data', description: 'Insère les classes, serveurs et templates de raid en base.')]
class SeedGameDataCommand extends Command
{
    private const CLASSES = [
        'Cra', 'Ecaflip', 'Eniripsa', 'Enutrof', 'Feca', 'Iop',
        'Osamodas', 'Pandawa', 'Roublard', 'Sacrieur', 'Sadida',
        'Sram', 'Xelor', 'Zobal', 'Steamer', 'Eliotrope', 'Huppermage', 'Ouginak',
    ];

    private const SERVERS = [
        'Draconiros', 'Hell Mina', 'Imagiro', 'Ilyzaelle', 'Jahash',
        'Tylezia', 'Orukam', 'Ombre', 'Agride', 'Crail',
        'Boune', 'Fallanster', 'Galgarion', 'Kourial',
    ];

    /**
     * Définitions des templates de raid avec leurs énigmes.
     * Les titres d'énigmes sont provisoires — à mettre à jour via le futur panel admin.
     */
    private const RAID_TEMPLATES = [
        [
            'name' => 'Belladone',
            'min'  => 8,
            'max'  => 12,
            'enigmes' => [
                'Enigme 1',
                'Enigme 2',
                'Enigme 3',
                'Enigme 4',
            ],
        ],
        [
            'name' => 'Gigalodon',
            'min'  => 12,
            'max'  => 16,
            'enigmes' => [
                'Enigme 1',
                'Enigme 2',
                'Enigme 3',
                'Enigme 4',
            ],
        ],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $added = 0;

        // Classes
        $classRepo = $this->em->getRepository(GameClass::class);
        foreach (self::CLASSES as $name) {
            if (!$classRepo->findOneBy(['name' => $name])) {
                $this->em->persist((new GameClass())->setName($name));
                $added++;
            }
        }

        // Serveurs
        $serverRepo = $this->em->getRepository(Server::class);
        foreach (self::SERVERS as $name) {
            if (!$serverRepo->findOneBy(['name' => $name])) {
                $this->em->persist((new Server())->setName($name));
                $added++;
            }
        }

        // Templates de raid + énigmes
        $templateRepo      = $this->em->getRepository(RaidTemplate::class);
        $enigmeTemplateRepo = $this->em->getRepository(EnigmeTemplate::class);

        foreach (self::RAID_TEMPLATES as $data) {
            $template = $templateRepo->findOneBy(['name' => $data['name']]);

            if (!$template) {
                $template = (new RaidTemplate())
                    ->setName($data['name'])
                    ->setMinParticipants($data['min'])
                    ->setMaxParticipants($data['max']);
                $this->em->persist($template);
                $added++;
            }

            // Flush now so the template gets an ID before we link EnigmeTemplates
            $this->em->flush();

            foreach ($data['enigmes'] as $order => $title) {
                $orderNumber = $order + 1;
                $existing = $enigmeTemplateRepo->findOneBy([
                    'raidTemplate' => $template,
                    'orderNumber'  => $orderNumber,
                ]);
                if (!$existing) {
                    $et = (new EnigmeTemplate())
                        ->setRaidTemplate($template)
                        ->setTitle($title)
                        ->setOrderNumber($orderNumber);
                    $this->em->persist($et);
                    $added++;
                }
            }
        }

        $this->em->flush();
        $io->success("$added entrée(s) insérée(s).");

        return Command::SUCCESS;
    }
}
