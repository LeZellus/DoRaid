<?php

namespace App\Controller;

use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Form\RaidType;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidCommentRepository;
use App\Repository\RaidParticipantRepository;
use App\Repository\RaidRepository;
use App\Repository\RaidTemplateRepository;
use App\Repository\ServerRepository;
use App\Service\DiscordNotifier;
use App\Service\EnigmeSyncService;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/raids')]
class RaidController extends AbstractController
{
    use CsrfGuardTrait;

    public function __construct(private readonly EnigmeSyncService $enigmeSyncService) {}

    #[Route('', name: 'app_raid_index')]
    public function index(
        Request $request,
        RaidRepository $raidRepo,
        ServerRepository $serverRepo,
        GuildMembershipRepository $membershipRepo,
        RaidParticipantRepository $participantRepo,
    ): Response {
        $user       = $this->getUser();
        $serverName = $request->query->get('server');
        $filterType = $request->query->get('type');

        $userGuildIds = [];
        if ($user) {
            foreach ($membershipRepo->findConfirmedForUser($user) as $m) {
                $userGuildIds[] = $m->getGuild()->getId();
            }
        }

        $filterNotFull = (bool) $request->query->get('not_full');
        $filterSoon    = (bool) $request->query->get('soon');

        $now   = new \DateTimeImmutable();
        $raids = $raidRepo->findVisibleForUser($userGuildIds, $serverName ?: null);

        $raidTypes = [];
        foreach ($raids as $r) {
            $name = $r->getRaidTemplate()->getName();
            if (!isset($raidTypes[$name])) {
                $raidTypes[$name] = $name;
            }
        }
        ksort($raidTypes);

        $open = array_filter($raids, fn($r) => $r->getStatus() === RaidStatus::Open);

        if ($filterType) {
            $open = array_filter($open, fn($r) => $r->getRaidTemplate()->getName() === $filterType);
        }

        $upcomingRaids = array_values(array_filter($open, fn($r) => $r->getScheduledAt() && $r->getScheduledAt() > $now));
        usort($upcomingRaids, fn($a, $b) => $a->getScheduledAt() <=> $b->getScheduledAt());

        $startedRaids = array_values(array_filter($open, fn($r) => $r->getScheduledAt() && $r->getScheduledAt() <= $now));
        usort($startedRaids, fn($a, $b) => $b->getScheduledAt() <=> $a->getScheduledAt());

        $ongoingRaids = array_values(array_filter($open, fn($r) => !$r->getScheduledAt()));
        usort($ongoingRaids, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        if ($filterNotFull) {
            $upcomingRaids = array_values(array_filter($upcomingRaids, fn($r) => !$r->isFull()));
            $startedRaids  = array_values(array_filter($startedRaids,  fn($r) => !$r->isFull()));
            $ongoingRaids  = array_values(array_filter($ongoingRaids,  fn($r) => !$r->isFull()));
        }

        if ($filterSoon) {
            $threshold     = $now->modify('+48 hours');
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
            'raidTypes'        => array_keys($raidTypes),
            'userGuildIds'     => $userGuildIds,
            'myParticipations' => $myParticipations,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/creer', name: 'app_raid_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        GuildRepository $guildRepo,
        CharacterRepository $charRepo,
        RaidTemplateRepository $templateRepo,
        DiscordNotifier $discord,
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
            $template  = $templateRepo->find((int) $request->request->get('raid_template_id'));
            $character = $charRepo->find((int) $request->request->get('character_id'));

            if (!$template) {
                $this->addFlash('error', 'Veuillez sélectionner un type de raid.');
                return $this->render('raid/new.html.twig', [
                    'form' => $form, 'guild' => $guild,
                    'characters' => $characters, 'templates' => $templates,
                ]);
            }

            if (!$character || $character->getUser()->getId() !== $this->getUser()->getId()) {
                throw $this->createAccessDeniedException();
            }

            $raid->setGuild($guild)->setCreator($character)->setRaidTemplate($template);
            $em->persist($raid);
            $em->persist((new RaidParticipant())->setRaid($raid)->setCharacter($character)->setStatus(RaidParticipantStatus::Accepted));
            $this->enigmeSyncService->syncFromTemplate($raid, $em);
            $em->flush();

            $discord->notifyRaidCreated($raid);

            $this->addFlash('success', 'Raid ' . $template->getName() . ' créé !');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        return $this->render('raid/new.html.twig', [
            'form'       => $form,
            'guild'      => $guild,
            'characters' => $characters,
            'templates'  => $templates,
        ]);
    }

    #[Route('/{id}/participants', name: 'app_raid_participants')]
    public function participants(Raid $raid, CharacterRepository $charRepo): Response
    {
        if ($r = $this->checkRaidAccess($raid, $charRepo)) return $r;

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
        EntityManagerInterface $em,
    ): Response {
        if ($r = $this->checkRaidAccess($raid, $charRepo)) return $r;

        if ($this->enigmeSyncService->syncFromTemplate($raid, $em)) {
            $em->flush();
        }

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

    private function checkRaidAccess(Raid $raid, CharacterRepository $charRepo): ?Response
    {
        if ($raid->isPublic()) {
            return null;
        }
        $viewer = $this->getUser();
        if (!$viewer) {
            $this->addFlash('error', 'Ce raid est privé. Connectez-vous pour y accéder.');
            return $this->redirectToRoute('app_login');
        }
        $isMember = !empty($charRepo->findConfirmedInGuild($viewer, $raid->getGuild()));
        if (!$isMember && !$raid->isCreatedBy($viewer)) {
            $this->addFlash('error', 'Ce raid est privé et réservé aux membres de la guilde.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $raid->getGuild()->getSlug()]);
        }
        return null;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/modifier', name: 'app_raid_edit', methods: ['GET', 'POST'])]
    public function edit(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        if (!$raid->isCreatedBy($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

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
    public function apply(Raid $raid, Request $request, CharacterRepository $charRepo, EntityManagerInterface $em): Response
    {
        $this->requireCsrfToken('apply_raid_' . $raid->getId(), $request);

        if (!$raid->isPublic()) {
            $isMember = !empty($charRepo->findConfirmedInGuild($this->getUser(), $raid->getGuild()));
            if (!$isMember) {
                $this->addFlash('error', 'Ce raid est privé et réservé aux membres de la guilde.');
                return $this->redirectToRoute('app_guild_show', ['slug' => $raid->getGuild()->getSlug()]);
            }
        }

        if ($raid->getStatus() !== RaidStatus::Open) {
            $this->addFlash('error', 'Ce raid est terminé.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $character = $charRepo->find((int) $request->request->get('character_id'));

        if (!$character || $character->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($character->getServer()->getId() !== $raid->getGuild()->getServer()->getId()) {
            $this->addFlash('error', 'Ce personnage n\'est pas sur le même serveur que ce raid.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        if ($raid->isParticipant($character)) {
            $this->addFlash('error', 'Ce personnage a déjà candidaté à ce raid.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        if ($raid->isFull()) {
            $this->addFlash('error', 'Ce raid est complet.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $em->persist((new RaidParticipant())->setRaid($raid)->setCharacter($character));
        $em->flush();

        $this->addFlash('success', 'Candidature de ' . $character->getName() . ' envoyée ! Le créateur du raid validera votre participation.');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/accepter', name: 'app_raid_accept', methods: ['POST'])]
    public function accept(RaidParticipant $participant, Request $request, EntityManagerInterface $em): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('accept_' . $participant->getId(), $request);

        if (!$raid->isCreatedBy($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $participant->setStatus(RaidParticipantStatus::Accepted);
        $em->flush();

        $this->addFlash('success', $participant->getCharacter()->getName() . ' a été accepté dans le raid !');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/clore', name: 'app_raid_close', methods: ['POST'])]
    public function close(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireCsrfToken('close_raid_' . $raid->getId(), $request);

        if (!$raid->isCreatedBy($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $raid->setStatus(RaidStatus::Closed);
        $em->flush();

        $this->addFlash('success', 'Raid marqué comme terminé.');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/participants/{id}/exclure', name: 'app_raid_kick', methods: ['POST'])]
    public function kick(RaidParticipant $participant, Request $request, EntityManagerInterface $em): Response
    {
        $raid = $participant->getRaid();
        $this->requireCsrfToken('kick_' . $participant->getId(), $request);

        if (!$raid->isCreatedBy($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $name = $participant->getCharacter()->getName();
        $em->remove($participant);
        $em->flush();

        $this->addFlash('success', $name . ' a été retiré du raid.');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/supprimer', name: 'app_raid_delete', methods: ['POST'])]
    public function delete(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireCsrfToken('delete_raid_' . $raid->getId(), $request);

        if (!$raid->isCreatedBy($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $guildSlug = $raid->getGuild()->getSlug();
        $em->remove($raid);
        $em->flush();

        $this->addFlash('success', 'Raid supprimé.');
        return $this->redirectToRoute('app_guild_show', ['slug' => $guildSlug]);
    }
}
