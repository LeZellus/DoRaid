<?php

namespace App\Command;

use App\Entity\Gem;
use App\Entity\Mob;
use App\Entity\MobDropRate;
use App\Entity\RaidTemplate;
use App\Entity\Salle;
use App\Entity\SalleComposition;
use App\Entity\SalleCompositionMob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Insère les données de référence du répartiteur de loot pour le raid Gigalodon, reprises
 * telles quelles de "Raid data - Feuille 1.pdf" (public/uploads) : gemmes, mobs avec leurs
 * taux de drop, et les 6 salles avec leurs 3 compositions de mobs chacune.
 */
#[AsCommand(name: 'app:seed:raid-loot-data', description: 'Insère les gemmes, mobs et salles du répartiteur de loot (Gigalodon).')]
class SeedRaidLootDataCommand extends Command
{
    private const GEMS = [
        'Quartz'     => 2,
        'Opale'      => 4,
        'Amazonite'  => 6,
        'Aventurine' => 10,
        'Lapiz'      => 15,
        'Jais'       => 20,
        'Onyx'       => 30,
    ];

    /** Taux de drop par mob, dans l'ordre des gemmes ci-dessus. */
    private const MOBS = [
        'madrepire'  => [0.30, 0.20, 0.10, 0.05, 0.01, 0.005, 0.001],
        'kokayou'    => [0.40, 0.30, 0.20, 0.10, 0.05, 0.01, 0.005],
        'leviatank'  => [0.50, 0.40, 0.30, 0.20, 0.10, 0.05, 0.01],
        'calarmure'  => [0.60, 0.50, 0.40, 0.30, 0.20, 0.10, 0.05],
        'ecaillon'   => [0.70, 0.60, 0.50, 0.40, 0.30, 0.20, 0.10],
        "krak'haine" => [0.80, 0.70, 0.60, 0.50, 0.40, 0.30, 0.20],
    ];

    /**
     * Salles [levelMin, levelMax] et leurs 3 compositions (quantités par mob).
     * @var array<int, array{levelMin: int, levelMax: int, compositions: array<int, array<string, int>>}>
     */
    private const SALLES = [
        ['levelMin' => 10, 'levelMax' => 14, 'compositions' => [
            ['madrepire' => 1, "krak'haine" => 2],
            ['leviatank' => 1, 'calarmure' => 1, "krak'haine" => 1],
            ['kokayou' => 1, 'leviatank' => 1, 'calarmure' => 1, 'ecaillon' => 1, "krak'haine" => 1],
        ]],
        ['levelMin' => 11, 'levelMax' => 14, 'compositions' => [
            ['leviatank' => 1, "krak'haine" => 1],
            ['calarmure' => 1, 'ecaillon' => 1, "krak'haine" => 1],
            ['kokayou' => 1, 'leviatank' => 1, 'calarmure' => 1, 'ecaillon' => 1, "krak'haine" => 2],
        ]],
        ['levelMin' => 12, 'levelMax' => 14, 'compositions' => [
            ['kokayou' => 1, "krak'haine" => 1],
            ['kokayou' => 1, 'ecaillon' => 1, "krak'haine" => 1],
            ['madrepire' => 2, 'calarmure' => 1, 'ecaillon' => 1, "krak'haine" => 1],
        ]],
        ['levelMin' => 12, 'levelMax' => 13, 'compositions' => [
            [],
            [],
            [],
        ]],
        ['levelMin' => 11, 'levelMax' => 13, 'compositions' => [
            ['ecaillon' => 2, "krak'haine" => 1],
            ['kokayou' => 1, 'calarmure' => 2, 'ecaillon' => 1, "krak'haine" => 1],
            ['calarmure' => 1, 'ecaillon' => 2, "krak'haine" => 2],
        ]],
        ['levelMin' => 10, 'levelMax' => 13, 'compositions' => [
            [],
            [],
            ['madrepire' => 1, 'kokayou' => 1, 'ecaillon' => 1, "krak'haine" => 2],
        ]],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $added = 0;

        $template = $this->em->getRepository(RaidTemplate::class)->findOneBy(['name' => 'Gouffre du Gigalodon']);
        if (!$template) {
            $io->error('Le type de raid "Gouffre du Gigalodon" est introuvable. Lancez d\'abord app:seed:game-data.');
            return Command::FAILURE;
        }

        // Gemmes
        $gemRepo = $this->em->getRepository(Gem::class);
        $gems    = [];
        foreach (self::GEMS as $name => $value) {
            $gem = $gemRepo->findOneBy(['name' => $name]);
            if (!$gem) {
                $gem = (new Gem())->setName($name)->setValue($value);
                $this->em->persist($gem);
                $added++;
            }
            $gems[$name] = $gem;
        }
        $this->em->flush();

        // Mobs + taux de drop
        $mobRepo   = $this->em->getRepository(Mob::class);
        $gemNames  = array_keys(self::GEMS);
        $mobs      = [];
        foreach (self::MOBS as $name => $probabilities) {
            $mob = $mobRepo->findOneBy(['raidTemplate' => $template, 'name' => $name]);
            if (!$mob) {
                $mob = (new Mob())->setRaidTemplate($template)->setName($name);
                $this->em->persist($mob);
                $added++;

                foreach ($gemNames as $i => $gemName) {
                    $rate = (new MobDropRate())->setMob($mob)->setGem($gems[$gemName])->setProbability($probabilities[$i]);
                    $this->em->persist($rate);
                }
            }
            $mobs[$name] = $mob;
        }
        $this->em->flush();

        // Salles + compositions
        $salleRepo = $this->em->getRepository(Salle::class);
        foreach (self::SALLES as $index => $data) {
            $orderNumber = $index + 1;
            $salle       = $salleRepo->findOneBy(['raidTemplate' => $template, 'orderNumber' => $orderNumber]);
            if (!$salle) {
                $salle = (new Salle())
                    ->setRaidTemplate($template)
                    ->setName('Salle ' . $orderNumber)
                    ->setLevelMin($data['levelMin'])
                    ->setLevelMax($data['levelMax'])
                    ->setOrderNumber($orderNumber);
                $this->em->persist($salle);
                $added++;

                foreach ($data['compositions'] as $compoIndex => $quantities) {
                    $composition = (new SalleComposition())->setSalle($salle)->setOrderNumber($compoIndex + 1);
                    $this->em->persist($composition);

                    foreach ($quantities as $mobName => $quantity) {
                        $this->em->persist(
                            (new SalleCompositionMob())->setComposition($composition)->setMob($mobs[$mobName])->setQuantity($quantity)
                        );
                    }
                }
            }
        }

        $this->em->flush();
        $io->success("$added entrée(s) insérée(s).");

        return Command::SUCCESS;
    }
}
