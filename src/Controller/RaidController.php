<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Exception\BusinessRuleException;
use App\Form\RaidType;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidCommentRepository;
use App\Repository\RaidParticipantRepository;
use App\Repository\RaidRepository;
use App\Repository\RaidTemplateRepository;
use App\Repository\ServerRepository;
use App\Security\RaidVoter;
use App\Service\RaidService;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/raids')]
class RaidController extends AbstractController
{
    use CsrfGuardTrait;

    public function __construct(private readonly RaidService $raidService) {}

    #[Route('', name: 'app_raid_index')]
    public function index(
        Request $request,
        RaidRepository $raidRepo,
        ServerRepository $serverRepo,
        GuildMembershipRepository $membershipRepo,
        RaidParticipantRepository $participantRepo,
    ): Response {
        $user          = $this->getUser();
        $serverName    = $request->query->get('server') ?: null;
        $filterType    = $request->query->get('type');
        $filterNotFull = (bool) $request->query->get('not_full');
        $filterSoon    = (bool) $request->query->get('soon');

        $userGuildIds = $user
            ? array_map(fn($m) => $m->getGuild()->getId(), $membershipRepo->findConfirmedForUser($user))
            : [];

        $grouped = $raidRepo->findGroupedOpen($userGuildIds, $serverName);

        $upcomingRaids = $grouped['upcoming'];
        // Un raid "démarré" peut avoir dépassé sa durée planifiée depuis la dernière
        // exécution de app:close-expired-raids : on le clôture à la volée plutôt que
        // de l'afficher comme encore ouvert.
        $startedRaids  = array_values(array_filter(
            $grouped['started'],
            fn($r) => !$this->raidService->closeIfExpired($r)
        ));
        $ongoingRaids  = $grouped['ongoing'];

        // Collect available types from all open raids before applying the type filter
        $raidTypes = array_unique(array_map(
            fn($r) => $r->getRaidTemplate()->getName(),
            array_merge($upcomingRaids, $startedRaids, $ongoingRaids)
        ));
        sort($raidTypes);

        if ($filterType) {
            $byType        = fn($r) => $r->getRaidTemplate()->getName() === $filterType;
            $upcomingRaids = array_values(array_filter($upcomingRaids, $byType));
            $startedRaids  = array_values(array_filter($startedRaids,  $byType));
            $ongoingRaids  = array_values(array_filter($ongoingRaids,  $byType));
        }

        if ($filterNotFull) {
            $notFull       = fn($r) => !$r->isFull();
            $upcomingRaids = array_values(array_filter($upcomingRaids, $notFull));
            $startedRaids  = array_values(array_filter($startedRaids,  $notFull));
            $ongoingRaids  = array_values(array_filter($ongoingRaids,  $notFull));
        }

        if ($filterSoon) {
            $threshold     = (new \DateTimeImmutable())->modify('+48 hours');
            $upcomingRaids = array_values(array_filter($upcomingRaids, fn($r) => $r->getScheduledAt() <= $threshold));
            $startedRaids  = [];
            $ongoingRaids  = [];
        }

        $myParticipations = $user ? $participantRepo->findOpenParticipationsForUser($user) : [];

        return $this->render('raid/index.html.twig', [
            'upcomingRaids'    => $upcomingRaids,
            'startedRaids'     => $startedRaids,
            'ongoingRaids'     => $ongoingRaids,
            'servers'          => $serverRepo->findAll(),
            'currentServer'    => $serverName,
            'filterNotFull'    => $filterNotFull,
            'filterSoon'       => $filterSoon,
            'filterType'       => $filterType,
            'raidTypes'        => $raidTypes,
            'userGuildIds'     => $userGuildIds,
            'myParticipations' => $myParticipations,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/creer', name: 'app_raid_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        GuildRepository $guildRepo,
        CharacterRepository $charRepo,
        RaidTemplateRepository $templateRepo,
        RateLimiterFactory $createRaidLimiter,
    ): Response {
        $guild = $guildRepo->find((int) $request->query->get('guild'));

        if (!$guild) {
            throw $this->createNotFoundException('Guilde introuvable.');
        }

        $characters = $charRepo->findConfirmedInGuild($this->getUser(), $guild);

        if (empty($characters)) {
            $this->addFlash('error', 'Vous devez être membre confirmé de cette guilde pour créer un raid.');
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
            $character = $charRepo->find((int) $request->request->get('character_id'));

            if (!$template) {
                $this->addFlash('error', 'Veuillez sélectionner un type de raid.');
                return $this->render('raid/new.html.twig', [
                    'form' => $form, 'guild' => $guild,
                    'characters' => $characters, 'templates' => $templates,
                ]);
            }

            if (!$character || (int) $character->getUser()->getId() !== (int) $this->getUser()->getId()) {
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
    public function participants(Raid $raid): Response
    {
        if ($r = $this->checkRaidAccess($raid)) return $r;

        return $this->render('raid/participants.html.twig', [
            'raid'      => $raid,
            'isCreator' => $this->getUser() && $raid->isCreatedBy($this->getUser()),
        ]);
    }

    #[Route('/{id}', name: 'app_raid_show')]
    public function show(
        Raid $raid,
        CharacterRepository $charRepo,
        RaidCommentRepository $commentRepo,
    ): Response {
        if ($r = $this->checkRaidAccess($raid)) return $r;

        $this->raidService->closeIfExpired($raid);
        $this->raidService->syncEnigmes($raid);

        $currentUser = $this->getUser();
        $userId      = $currentUser ? (int) $currentUser->getId() : null;
        $eligible    = $currentUser ? $charRepo->findAllEligibleForRaid($currentUser, $raid) : [];
        $isCreator   = $currentUser && $raid->isCreatedBy($currentUser);

        $acceptedCharacters  = [];
        $pendingApplications = [];
        $participantsByUser  = [];
        foreach ($raid->getParticipants() as $p) {
            $participantsByUser[(int) $p->getCharacter()->getUser()->getId()] = $p->getCharacter();
            if ($userId && (int) $p->getCharacter()->getUser()->getId() === $userId) {
                if ($p->getStatus() === RaidParticipantStatus::Accepted) {
                    $acceptedCharacters[] = $p->getCharacter();
                } else {
                    $pendingApplications[] = $p;
                }
            }
        }

        return $this->render('raid/show.html.twig', [
            'raid'                => $raid,
            'eligible'            => $eligible,
            'isCreator'           => $isCreator,
            'acceptedCharacters'  => $acceptedCharacters,
            'pendingApplications' => $pendingApplications,
            'participantsByUser'  => $participantsByUser,
            'rootComments'        => $commentRepo->findRootByRaid($raid),
        ]);
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
    public function apply(Raid $raid, Request $request, CharacterRepository $charRepo, RateLimiterFactory $applyRaidLimiter): Response
    {
        $this->requireCsrfToken('apply_raid_' . $raid->getId(), $request);

        $limiter = $applyRaidLimiter->create($this->getUser()->getUserIdentifier());
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de candidatures en peu de temps. Réessayez dans quelques instants.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $character = $charRepo->find((int) $request->request->get('character_id'));

        if (!$character || (int) $character->getUser()->getId() !== (int) $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->raidService->applyToRaid($raid, $character, $this->getUser());
            $this->addFlash('success', 'Candidature de ' . $character->getName() . ' envoyée ! Le créateur du raid validera votre participation.');
        } catch (BusinessRuleException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/accepter', name: 'app_raid_accept', methods: ['POST'])]
    public function accept(RaidParticipant $participant, Request $request): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('accept_' . $participant->getId(), $request);
        $this->denyAccessUnlessGranted(RaidVoter::CREATOR, $raid);

        $this->raidService->acceptParticipant($participant);

        $this->addFlash('success', $participant->getCharacter()->getName() . ' a été accepté dans le raid !');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('RAID_CREATOR', subject: 'raid')]
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/clore', name: 'app_raid_close', methods: ['POST'])]
    public function close(Raid $raid, Request $request): Response
    {
        $this->requireCsrfToken('close_raid_' . $raid->getId(), $request);
        $this->raidService->closeRaid($raid);

        $this->addFlash('success', 'Raid marqué comme terminé.');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
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
