<?php

namespace App\Controller;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Form\GuildType;
use App\Repository\CharacterRepository;
use App\Repository\GuildRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/guilds')]
class GuildController extends AbstractController
{
    #[Route('', name: 'app_guild_index')]
    public function index(GuildRepository $repo): Response
    {
        $guilds = $repo->findAllOrdered();

        $byServer = [];
        foreach ($guilds as $guild) {
            $byServer[$guild->getServer()->getName()][] = $guild;
        }

        return $this->render('guild/index.html.twig', ['byServer' => $byServer]);
    }

    #[Route('/new', name: 'app_guild_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $guild = new Guild();
        $form = $this->createForm(GuildType::class, $guild);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $guild->setOwner($this->getUser());
            $em->persist($guild);
            $em->flush();

            $this->addFlash('success', 'Guilde créée ! Choisissez votre personnage meneur ci-dessous.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        return $this->render('guild/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_guild_show')]
    public function show(Guild $guild, CharacterRepository $charRepo): Response
    {
        $eligible = $charRepo->findEligibleForGuild($this->getUser(), $guild->getServer());
        $isOwner = $guild->getOwner() === $this->getUser();

        return $this->render('guild/show.html.twig', [
            'guild'    => $guild,
            'eligible' => $eligible,
            'isOwner'  => $isOwner,
        ]);
    }

    #[Route('/{id}/join', name: 'app_guild_join', methods: ['POST'])]
    public function join(Guild $guild, Request $request, CharacterRepository $charRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('join_guild_' . $guild->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $character = $charRepo->find((int) $request->request->get('character_id'));

        if (!$character || $character->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($character->getServer() !== $guild->getServer()) {
            $this->addFlash('error', 'Ce personnage n\'est pas sur le même serveur que la guilde.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        if ($character->getMembership() !== null) {
            $this->addFlash('error', 'Ce personnage est déjà dans une guilde.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        // Owner of the guild always joins as leader; others are pending
        $isOwner = $guild->getOwner() === $this->getUser();
        $status = $isOwner && !$guild->hasLeader() ? MemberStatus::Leader : MemberStatus::Pending;

        $membership = (new GuildMembership())
            ->setGuild($guild)
            ->setCharacter($character)
            ->setStatus($status);

        $em->persist($membership);
        $em->flush();

        $msg = $status === MemberStatus::Leader
            ? $character->getName() . ' est maintenant meneur de ' . $guild->getName() . ' !'
            : $character->getName() . ' a demandé à rejoindre ' . $guild->getName() . '. En attente de validation.';

        $this->addFlash('success', $msg);
        return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
    }

    #[Route('/memberships/{id}/approve', name: 'app_guild_approve', methods: ['POST'])]
    public function approve(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $guild = $membership->getGuild();

        if (!$this->isCsrfTokenValid('membership_' . $membership->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($guild->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $membership->setStatus(MemberStatus::Member);
        $em->flush();

        $this->addFlash('success', $membership->getCharacter()->getName() . ' est maintenant membre.');
        return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
    }

    #[Route('/memberships/{id}/reject', name: 'app_guild_reject', methods: ['POST'])]
    public function reject(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $guild = $membership->getGuild();

        if (!$this->isCsrfTokenValid('membership_' . $membership->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($guild->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($membership);
        $em->flush();

        $this->addFlash('success', 'Demande refusée.');
        return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
    }

    #[Route('/memberships/{id}/leave', name: 'app_guild_leave', methods: ['POST'])]
    public function leave(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('leave_' . $membership->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getCharacter()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() === MemberStatus::Leader) {
            $this->addFlash('error', 'Le meneur ne peut pas quitter la guilde.');
            return $this->redirectToRoute('app_guild_show', ['id' => $membership->getGuild()->getId()]);
        }

        $guildId = $membership->getGuild()->getId();
        $em->remove($membership);
        $em->flush();

        $this->addFlash('success', 'Vous avez quitté la guilde.');

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_guild_show', ['id' => $guildId]);
    }
}
