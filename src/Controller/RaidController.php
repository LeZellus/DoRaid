<?php

namespace App\Controller;

use App\Entity\Enigme;
use App\Entity\MemberStatus;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Form\RaidType;
use App\Repository\CharacterRepository;
use App\Repository\EnigmeTemplateRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidRepository;
use App\Repository\RaidTemplateRepository;
use App\Repository\ServerRepository;
use App\Service\DiscordNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/raids')]
class RaidController extends AbstractController
{
    #[Route('', name: 'app_raid_index')]
    public function index(
        Request $request,
        RaidRepository $raidRepo,
        ServerRepository $serverRepo,
        GuildMembershipRepository $membershipRepo,
        EntityManagerInterface $em,
    ): Response {
        $user       = $this->getUser();
        $serverName = $request->query->get('server');

        $userGuildIds = [];
        $userGuilds   = [];
        if ($user) {
            foreach ($membershipRepo->findConfirmedForUser($user) as $m) {
                $userGuildIds[] = $m->getGuild()->getId();
                $userGuilds[$m->getGuild()->getId()] = $m->getGuild();
            }
        }

        $filterNotFull = (bool) $request->query->get('not_full');
        $filterSoon    = (bool) $request->query->get('soon');

        $now     = new \DateTimeImmutable();
        $raids   = $raidRepo->findVisibleForUser($userGuildIds, $serverName ?: null);
        $servers = $serverRepo->findAll();

        // Auto-close raids whose scheduled duration has elapsed
        $flush = false;
        foreach ($raids as $r) {
            if ($r->getStatus() === RaidStatus::Open) {
                $end = $r->getExpectedEndTime();
                if ($end !== null && $end <= $now) {
                    $r->setStatus(RaidStatus::Closed);
                    $flush = true;
                }
            }
        }
        if ($flush) {
            $em->flush();
        }

        $open = array_filter($raids, fn($r) => $r->getStatus() === RaidStatus::Open);

        // À venir : scheduledAt dans le futur
        $upcomingRaids = array_values(array_filter($open, fn($r) => $r->getScheduledAt() && $r->getScheduledAt() > $now));
        usort($upcomingRaids, fn($a, $b) => $a->getScheduledAt() <=> $b->getScheduledAt());

        // Commencés : scheduledAt dans le passé
        $startedRaids = array_values(array_filter($open, fn($r) => $r->getScheduledAt() && $r->getScheduledAt() <= $now));
        usort($startedRaids, fn($a, $b) => $b->getScheduledAt() <=> $a->getScheduledAt());

        // En cours : pas de date planifiée
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

        return $this->render('raid/index.html.twig', [
            'upcomingRaids'  => $upcomingRaids,
            'startedRaids'   => $startedRaids,
            'ongoingRaids'   => $ongoingRaids,
            'servers'        => $servers,
            'currentServer'  => $serverName,
            'filterNotFull'  => $filterNotFull,
            'filterSoon'     => $filterSoon,
            'userGuildIds'   => $userGuildIds,
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
        EnigmeTemplateRepository $enigmeTemplateRepo,
        DiscordNotifier $discord,
    ): Response {
        $guild = $guildRepo->find((int) $request->query->get('guild'));

        if (!$guild) {
            throw $this->createNotFoundException('Guilde introuvable.');
        }

        $characters = $charRepo->findConfirmedInGuild($this->getUser(), $guild);

        if (empty($characters)) {
            $this->addFlash('error', 'Vous devez être membre confirmé de cette guilde pour créer un raid.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
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

            if (!$character || $character->getUser() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            $raid->setGuild($guild)->setCreator($character)->setRaidTemplate($template);
            $em->persist($raid);
            $em->persist((new RaidParticipant())->setRaid($raid)->setCharacter($character)->setStatus(RaidParticipantStatus::Accepted));

            // Auto-create enigmas from the template definitions
            foreach ($enigmeTemplateRepo->findByTemplate($template) as $enigmeTemplate) {
                $em->persist((new Enigme())
                    ->setRaid($raid)
                    ->setOrderNumber($enigmeTemplate->getOrderNumber())
                    ->setSourceTemplate($enigmeTemplate)
                );
            }

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

    #[Route('/{id}', name: 'app_raid_show')]
    public function show(
        Raid $raid,
        CharacterRepository $charRepo,
        EnigmeTemplateRepository $enigmeTemplateRepo,
        EntityManagerInterface $em,
    ): Response {
        // Visibility check: private raids are guild-member only
        if (!$raid->isPublic()) {
            $viewer = $this->getUser();
            if (!$viewer) {
                $this->addFlash('error', 'Ce raid est privé. Connectez-vous pour y accéder.');
                return $this->redirectToRoute('app_login');
            }
            $isMember = !empty($charRepo->findConfirmedInGuild($viewer, $raid->getGuild()));
            $isCreatorUser = $raid->getCreator()->getUser()->getId() === $viewer->getId();
            if (!$isMember && !$isCreatorUser) {
                throw $this->createAccessDeniedException('Ce raid est privé.');
            }
        }

        // Sync: add any enigme templates added after the raid was created
        $existingIds = array_map(
            fn($e) => $e->getSourceTemplate()->getId(),
            $raid->getEnigmes()->toArray()
        );
        $needsFlush = false;
        foreach ($enigmeTemplateRepo->findByTemplate($raid->getRaidTemplate()) as $et) {
            if (!in_array($et->getId(), $existingIds, true)) {
                $em->persist((new Enigme())
                    ->setRaid($raid)
                    ->setOrderNumber($et->getOrderNumber())
                    ->setSourceTemplate($et)
                );
                $needsFlush = true;
            }
        }
        if ($needsFlush) {
            $em->flush();
        }

        $currentUser = $this->getUser();
        $userId      = $currentUser ? (int) $currentUser->getId() : null;
        $eligible    = $currentUser ? $charRepo->findEligibleForRaid($currentUser, $raid) : [];
        $isCreator   = $userId && (int) $raid->getCreator()->getUser()->getId() === $userId;

        $acceptedCharacters  = [];
        $pendingApplications = [];
        if ($userId) {
            foreach ($raid->getParticipants() as $p) {
                if ((int) $p->getCharacter()->getUser()->getId() === $userId) {
                    if ($p->getStatus() === RaidParticipantStatus::Accepted) {
                        $acceptedCharacters[] = $p->getCharacter();
                    } else {
                        $pendingApplications[] = $p;
                    }
                }
            }
        }

        return $this->render('raid/show.html.twig', [
            'raid'                => $raid,
            'eligible'            => $eligible,
            'isCreator'           => $isCreator,
            'acceptedCharacters'  => $acceptedCharacters,
            'pendingApplications' => $pendingApplications,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/modifier', name: 'app_raid_edit', methods: ['GET', 'POST'])]
    public function edit(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        if ($raid->getCreator()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(RaidType::class, $raid);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Raid mis à jour.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        return $this->render('raid/edit.html.twig', [
            'form' => $form,
            'raid' => $raid,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/candidater', name: 'app_raid_apply', methods: ['POST'])]
    public function apply(Raid $raid, Request $request, CharacterRepository $charRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('apply_raid_' . $raid->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getStatus() !== RaidStatus::Open) {
            $this->addFlash('error', 'Ce raid est terminé.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $character = $charRepo->find((int) $request->request->get('character_id'));

        if (!$character || $character->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $membership = $character->getMembership();
        if (!$membership || $membership->getGuild()->getId() !== $raid->getGuild()->getId() || $membership->getStatus() === MemberStatus::Pending) {
            $this->addFlash('error', 'Ce personnage n\'est pas membre confirmé de cette guilde.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        if ($raid->isParticipant($character)) {
            $this->addFlash('error', 'Ce personnage a déjà candidaté à ce raid.');
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

        if (!$this->isCsrfTokenValid('accept_' . $participant->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getCreator()->getUser() !== $this->getUser()) {
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
        if (!$this->isCsrfTokenValid('close_raid_' . $raid->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getCreator()->getUser() !== $this->getUser()) {
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

        if (!$this->isCsrfTokenValid('kick_' . $participant->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getCreator()->getUser() !== $this->getUser()) {
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
        if (!$this->isCsrfTokenValid('delete_raid_' . $raid->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getCreator()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $guildSlug = $raid->getGuild()->getSlug();
        $em->remove($raid);
        $em->flush();

        $this->addFlash('success', 'Raid supprimé.');
        return $this->redirectToRoute('app_guild_show', ['slug' => $guildSlug]);
    }
}
