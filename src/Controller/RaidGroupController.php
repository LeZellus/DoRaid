<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidGroup;
use App\Entity\RaidParticipant;
use App\Entity\RaidStatus;
use App\Exception\BusinessRuleException;
use App\Repository\RaidGroupRepository;
use App\Security\RaidVoter;
use App\Service\RaidGroupService;
use App\Traits\CsrfGuardTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;
use Symfony\UX\Turbo\TurboStreamResponse;

#[IsGranted('ROLE_USER')]
#[Route('/raids')]
class RaidGroupController extends AbstractController
{
    use CsrfGuardTrait;

    public function __construct(private readonly RaidGroupService $raidGroupService) {}

    #[Route('/{id}/groupes/creer', name: 'app_raid_group_new', methods: ['POST'])]
    public function new(Raid $raid, Request $request): Response
    {
        $this->requireCsrfToken('new_group_' . $raid->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        return $this->respondToGroupAction(
            $raid,
            $request,
            fn() => $this->raidGroupService->createGroup($raid),
            'Groupe créé.'
        );
    }

    #[Route('/groupes/{id}/renommer', name: 'app_raid_group_rename', methods: ['POST'])]
    public function rename(RaidGroup $group, Request $request): Response
    {
        $raid = $group->getRaid();
        $this->requireCsrfToken('rename_group_' . $group->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        return $this->respondToGroupAction(
            $raid,
            $request,
            fn() => $this->raidGroupService->renameGroup($group, $request->request->get('label')),
            'Groupe renommé.'
        );
    }

    #[Route('/groupes/{id}/supprimer', name: 'app_raid_group_delete', methods: ['POST'])]
    public function delete(RaidGroup $group, Request $request): Response
    {
        $raid = $group->getRaid();
        $this->requireCsrfToken('delete_group_' . $group->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        return $this->respondToGroupAction(
            $raid,
            $request,
            fn() => $this->raidGroupService->deleteGroup($group),
            'Groupe supprimé.'
        );
    }

    #[Route('/participants/{id}/assigner', name: 'app_raid_participant_assign', methods: ['POST'])]
    public function assign(RaidParticipant $participant, Request $request, RaidGroupRepository $groupRepo): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('assign_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $groupId = $request->request->get('group_id');
        $name    = $participant->getCharacter()->getName();

        // Résolu en amont (pas dans la closure d'action) : un message de succès défini via
        // une simple valeur (pas de callable) doit déjà connaître le résultat au moment de
        // l'appel — une closure capturerait $group par valeur avant que l'action ne l'assigne.
        $group         = $groupId ? $groupRepo->find((int) $groupId) : null;
        $groupNotFound = $groupId && !$group;

        return $this->respondToGroupAction(
            $raid,
            $request,
            function () use ($groupNotFound, $participant, $group) {
                if ($groupNotFound) {
                    throw new BusinessRuleException('Groupe introuvable.');
                }
                $this->raidGroupService->assignParticipant($participant, $group);
            },
            $group
                ? $name . ' a été assigné à ' . ($group->getLabel() ?: 'un groupe') . '.'
                : $name . ' n\'est plus assigné à un groupe.'
        );
    }

    /**
     * Exécute une action de gestion des groupes puis répond soit par un Turbo Stream
     * (rafraîchit la popup en place, sans recharger la page — client avec JS/Turbo),
     * soit par le flash + redirect classique (dégradation gracieuse sans JS).
     */
    private function respondToGroupAction(Raid $raid, Request $request, callable $action, string|callable $successMessage): Response
    {
        $type = 'success';
        try {
            $action();
            $message = \is_callable($successMessage) ? $successMessage() : $successMessage;
        } catch (BusinessRuleException $e) {
            $type    = 'error';
            $message = $e->getMessage();
        }

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $isOpen = $raid->getStatus() === RaidStatus::Open;
            $panel  = $this->renderView('raid/_groups_panel.html.twig', [
                'raid'      => $raid,
                'isCreator' => $this->isGranted(RaidVoter::CREATOR, $raid),
                'raidOpen'  => $isOpen,
                'flash'     => ['type' => $type, 'message' => $message],
            ]);
            $count = $this->renderView('raid/_groups_band_count.html.twig', ['raid' => $raid]);

            return (new TurboStreamResponse())
                ->replace('#raid-groups-frame', $panel)
                ->replace('#raid-groups-band-count', $count);
        }

        $this->addFlash($type, $message);
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId(), '_fragment' => 'groupes']);
    }
}
