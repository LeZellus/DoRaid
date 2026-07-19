<?php

namespace App\Controller;

use App\Entity\Mob;
use App\Entity\RaidTemplate;
use App\Entity\Salle;
use App\Repository\GemRepository;
use App\Repository\MobRepository;
use App\Repository\RaidTemplateRepository;
use App\Repository\SalleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Outil de simulation autonome : score de loot espéré par salle/composition et assignation
 * optimale pour des tailles de groupe hypothétiques. Accessible à tout moment, indépendamment
 * de tout raid réellement planifié ou en cours (contrairement à la gestion des groupes d'un
 * raid précis, voir RaidGroupController).
 *
 * Le contrôleur ne fait que sérialiser les données de référence (probabilités de drop,
 * quantités par composition) ; tout le calcul (espérances, scores, optimisation) est effectué
 * côté client par le contrôleur Stimulus raid-loot-dispatcher, pour un recalcul instantané
 * pendant l'édition — cf. public/uploads/raid_optimizer.html, le prototype repris ici.
 */
class RaidLootDispatcherController extends AbstractController
{
    public function __construct(
        private readonly GemRepository $gemRepo,
        private readonly MobRepository $mobRepo,
        private readonly SalleRepository $salleRepo,
        private readonly RaidTemplateRepository $templateRepo,
    ) {}

    #[Route('/outils/repartiteur-loot', name: 'app_loot_dispatcher')]
    public function index(Request $request): Response
    {
        $templates = $this->templateRepo->findAllOrdered();

        $templateId       = (int) $request->query->get('raid_template', 0);
        $selectedTemplate = $templateId !== 0
            ? current(array_filter($templates, fn(RaidTemplate $t) => $t->getId() === $templateId)) ?: null
            : ($templates[0] ?? null);

        $gems = $this->gemRepo->findAllOrdered();
        $mobs = $selectedTemplate ? $this->mobRepo->findByRaidTemplateOrdered($selectedTemplate) : [];
        $salles = $selectedTemplate ? $this->salleRepo->findByRaidTemplateOrdered($selectedTemplate) : [];

        return $this->render('raid_loot_dispatcher/index.html.twig', [
            'templates'        => $templates,
            'selectedTemplate' => $selectedTemplate,
            'gemsJson'         => array_map(fn($g) => ['name' => $g->getName(), 'value' => $g->getValue()], $gems),
            'mobsJson'         => array_map(fn(Mob $mob) => $this->serializeMob($mob, $gems), $mobs),
            'sallesJson'       => array_map($this->serializeSalle(...), $salles),
            'hasData'          => $mobs !== [],
        ]);
    }

    private function serializeMob(Mob $mob, array $gems): array
    {
        $probabilityByGemId = [];
        foreach ($mob->getDropRates() as $rate) {
            $probabilityByGemId[$rate->getGem()->getId()] = $rate->getProbability();
        }

        return [
            'name'  => $mob->getName(),
            'probs' => array_map(fn($gem) => round(($probabilityByGemId[$gem->getId()] ?? 0.0) * 100, 3), $gems),
        ];
    }

    private function serializeSalle(Salle $salle): array
    {
        return [
            'name'  => $salle->getName(),
            'range' => $salle->getLevelRange(),
            'compositions' => array_map(function ($composition) {
                $counts = [];
                foreach ($composition->getMobQuantities() as $mobQuantity) {
                    $counts[$mobQuantity->getMob()->getName()] = $mobQuantity->getQuantity();
                }

                return [
                    'label'  => $composition->getLabel() ?? ('Composition ' . $composition->getOrderNumber()),
                    'counts' => $counts,
                ];
            }, $salle->getCompositions()->toArray()),
        ];
    }
}
