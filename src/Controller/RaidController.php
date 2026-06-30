<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Exception\BusinessRuleException;
use App\Form\RaidType;
use App\Repository\CharacterRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidParticipantRepository;
use App\Repository\RaidRepository;
use App\Repository\RaidTemplateRepository;
use App\Repository\ServerRepository;
use App\Security\RaidVoter;
use App\Service\RaidService;
use App\Traits\BusinessRuleFlashTrait;
use App\Traits\CharacterOwnershipTrait;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/raids')]
class RaidController extends AbstractController
{
    use BusinessRuleFlashTrait;
    use CharacterOwnershipTrait;
    use CsrfGuardTrait;

    public function __construct(private readonly RaidService $raidService) {}

    #[Route('', name: 'app_raid_index')]
    public function index(Request $request, ServerRepository $serverRepo): Response
    {
        $serverName    = $request->query->get('server') ?: null;
        $filterType    = $request->query->get('type');
        $filterNotFull = (bool) $request->query->get('not_full');
        $filterSoon    = (bool) $request->query->get('soon');

        $data = $this->raidService->buildIndexData(
            $this->getUser(),
            $serverName,
            $filterType,
            $filterNotFull,
            $filterSoon,
        );

        return $this->render('raid/index.html.twig', $data + [
            'servers'       => $serverRepo->findAll(),
            'currentServer' => $serverName,
            'filterNotFull' => $filterNotFull,
            'filterSoon'    => $filterSoon,
            'filterType'    => $filterType,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/creer', name: 'app_raid_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        GuildRepository $guildRepo,
        CharacterRepository $charRepo,
        RaidTemplateRepository $templateRepo,
        RateLimiterFactoryInterface $createRaidLimiter,
    ): Response {
        $guild = $guildRepo->find((int) $request->query->get('guild'));

        if (!$guild) {
            throw $this->createNotFoundException('Guilde introuvable.');
        }

        $characters = $charRepo->findCanCreateRaidsInGuild($this->getUser(), $guild);

        if (empty($characters)) {
            $this->addFlash('error', 'Vous devez être meneur ou avoir reçu la permission de créer des raids dans cette guilde.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        $templates = $templateRepo->findAllOrdered();
        $raid      = new Raid();
        $form      = $this->createForm(RaidType::class, $raid);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $createRaidLimiter->create($this->getUser()->getUserIdentifier());
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de raids créés en peu de temps. Réessayez dans quelques instants.');
                return $this->render('raid/new.html.twig', [
                    'form' => $form, 'guild' => $guild,
                    'characters' => $characters, 'templates' => $templates,
                ]);
            }

            $template  = $templateRepo->find((int) $request->request->get('raid_template_id'));
            $character = $this->resolveOwnCharacter($request, $charRepo);

            if (!$template) {
                $this->addFlash('error', 'Veuillez sélectionner un type de raid.');
                return $this->render('raid/new.html.twig', [
                    'form' => $form, 'guild' => $guild,
                    'characters' => $characters, 'templates' => $templates,
                ]);
            }

            if (!$character) {
                throw $this->createAccessDeniedException();
            }

            try {
                $this->raidService->createRaid($raid, $guild, $character, $template);
                $this->addFlash('success', 'Raid ' . $template->getName() . ' créé !');
                return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
            } catch (BusinessRuleException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->render('raid/new.html.twig', [
                    'form' => $form, 'guild' => $guild,
                    'characters' => $characters, 'templates' => $templates,
                ]);
            }
        }

        return $this->render('raid/new.html.twig', [
            'form'       => $form,
            'guild'      => $guild,
            'characters' => $characters,
            'templates'  => $templates,
        ]);
    }

    #[Route('/{id}/participants', name: 'app_raid_participants')]
    public function participants(int $id, RaidRepository $raidRepo, RaidParticipantRepository $participantRepo, CharacterRepository $charRepo): Response
    {
        $raid = $raidRepo->findWithParticipants($id);
        if (!$raid) {
            throw $this->createNotFoundException('Raid introuvable.');
        }

        if ($r = $this->checkRaidAccess($raid)) return $r;

        $currentUser    = $this->getUser();
        $isCreator      = $currentUser && $raid->isCreatedBy($currentUser);
        $canManageNotes = $isCreator || ($currentUser && !empty($charRepo->findCanCreateRaidsInGuild($currentUser, $raid->getGuild())));

        $characterIds = array_values(array_filter(
            $raid->getParticipants()->map(fn($p) => $p->getCharacter()->getId())->toArray(),
            fn($id) => $id !== null
        ));

        return $this->render('raid/participants.html.twig', [
            'raid'                => $raid,
            'isCreator'           => $isCreator,
            'canManageNotes'      => $canManageNotes,
            'isLeader'            => $currentUser && $raid->getGuild()->isLeaderOf($currentUser),
            'myOtherRaids'        => $isCreator ? $raidRepo->findOpenByGuild($raid->getGuild(), $raid) : [],
            'completedRaidCounts' => $participantRepo->countCompletedByCharacters($characterIds),
        ]);
    }

    #[Route('/{id}', name: 'app_raid_show')]
    public function show(int $id, RaidRepository $raidRepo): Response
    {
        $raid = $raidRepo->findWithParticipants($id);
        if (!$raid) {
            throw $this->createNotFoundException('Raid introuvable.');
        }

        if ($r = $this->checkRaidAccess($raid)) return $r;

        $this->raidService->closeIfExpired($raid);
        $this->raidService->syncEnigmes($raid);

        $data = $this->raidService->buildShowData($raid, $this->getUser());

        return $this->render('raid/show.html.twig', ['raid' => $raid] + $data);
    }

    private function checkRaidAccess(Raid $raid): ?Response
    {
        if ($this->isGranted(RaidVoter::VIEW, $raid)) {
            return null;
        }
        if (!$this->getUser()) {
            $this->addFlash('error', 'Ce raid est privé. Connectez-vous pour y accéder.');
            return $this->redirectToRoute('app_login');
        }
        $this->addFlash('error', 'Ce raid est privé et réservé aux membres de la guilde.');
        return $this->redirectToRoute('app_guild_show', ['slug' => $raid->getGuild()->getSlug()]);
    }

    #[IsGranted('RAID_CREATOR', subject: 'raid')]
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/modifier', name: 'app_raid_edit', methods: ['GET', 'POST'])]
    public function edit(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RaidType::class, $raid);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Raid mis à jour.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        return $this->render('raid/edit.html.twig', ['form' => $form, 'raid' => $raid]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/candidater', name: 'app_raid_apply', methods: ['POST'])]
    public function apply(Raid $raid, Request $request, CharacterRepository $charRepo, RateLimiterFactoryInterface $applyRaidLimiter): Response
    {
        $this->requireCsrfToken('apply_raid_' . $raid->getId(), $request);

        $limiter = $applyRaidLimiter->create($this->getUser()->getUserIdentifier());
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de candidatures en peu de temps. Réessayez dans quelques instants.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $character = $this->resolveOwnCharacter($request, $charRepo);

        if (!$character) {
            throw $this->createAccessDeniedException();
        }

        $this->handleBusinessRule(
            fn() => $this->raidService->applyToRaid($raid, $character, $this->getUser()),
            fn() => $raid->isFull()
                ? 'Candidature de ' . $character->getName() . ' envoyée ! Le raid est complet, vous êtes sur la liste des intéressés en cas de désistement.'
                : 'Candidature de ' . $character->getName() . ' envoyée ! Le créateur du raid validera votre participation.'
        );

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/accepter', name: 'app_raid_accept', methods: ['POST'])]
    public function accept(RaidParticipant $participant, Request $request): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('accept_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $this->handleBusinessRule(
            fn() => $this->raidService->acceptParticipant($participant),
            $participant->getCharacter()->getName() . ' a été accepté dans le raid !'
        );

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/remettre-en-attente', name: 'app_raid_unaccept', methods: ['POST'])]
    public function unaccept(RaidParticipant $participant, Request $request): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('unaccept_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $this->handleBusinessRule(
            fn() => $this->raidService->moveToWaitlist($participant),
            $participant->getCharacter()->getName() . ' a été replacé sur la liste des intéressés.'
        );

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/deplacer', name: 'app_raid_move', methods: ['POST'])]
    public function move(RaidParticipant $participant, Request $request, RaidRepository $raidRepo): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('move_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $targetRaid = $raidRepo->find((int) $request->request->get('target_raid_id'));
        if (!$targetRaid) {
            $this->addFlash('error', 'Raid de destination introuvable.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $name = $participant->getCharacter()->getName();
        $this->handleBusinessRule(
            fn() => $this->raidService->moveParticipant($participant, $targetRaid),
            $name . ' a été déplacé vers ' . $targetRaid->getRaidTemplate()->getName() . '.'
        );

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/nommer-createur', name: 'app_raid_transfer_creator', methods: ['POST'])]
    public function transferCreator(RaidParticipant $participant, Request $request): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('transfer_creator_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $this->handleBusinessRule(
            fn() => $this->raidService->transferCreator($raid, $participant->getCharacter()),
            $participant->getCharacter()->getName() . ' est maintenant créateur du raid.'
        );

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('RAID_CREATOR', subject: 'raid')]
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/clore', name: 'app_raid_close', methods: ['POST'])]
    public function close(Raid $raid, Request $request): Response
    {
        $this->requireCsrfToken('close_raid_' . $raid->getId(), $request);
        $this->handleBusinessRule(fn() => $this->raidService->closeRaid($raid), 'Raid marqué comme terminé.');

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/annuler', name: 'app_raid_cancel', methods: ['POST'])]
    public function cancel(RaidParticipant $participant, Request $request): Response
    {
        $raidId = $participant->getRaid()->getId();
        $this->requireCsrfToken('cancel_' . $participant->getId(), $request);

        $this->handleBusinessRule(
            fn() => $this->raidService->cancelParticipation($participant, $this->getUser()),
            'Votre participation a été annulée.'
        );

        return $this->redirectToRoute('app_raid_show', ['id' => $raidId]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/exclure', name: 'app_raid_kick', methods: ['POST'])]
    public function kick(RaidParticipant $participant, Request $request): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('kick_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $name = $participant->getCharacter()->getName();
        $this->raidService->kickParticipant($participant);

        $this->addFlash('success', $name . ' a été retiré du raid.');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('RAID_CREATOR', subject: 'raid')]
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/supprimer', name: 'app_raid_delete', methods: ['POST'])]
    public function delete(Raid $raid, Request $request): Response
    {
        $this->requireCsrfToken('delete_raid_' . $raid->getId(), $request);

        $guildSlug = $raid->getGuild()->getSlug();
        $this->raidService->deleteRaid($raid);

        $this->addFlash('success', 'Raid supprimé.');
        return $this->redirectToRoute('app_guild_show', ['slug' => $guildSlug]);
    }
}
