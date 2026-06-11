<?php

namespace App\Controller;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Form\GuildDescriptionType;
use App\Form\GuildType;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidRepository;
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
    public function show(Guild $guild, CharacterRepository $charRepo, RaidRepository $raidRepo, EntityManagerInterface $em): Response
    {
        $currentUser = $this->getUser();
        $isOwner     = $guild->getOwner()->getId() === $currentUser->getId();

        // Auto-approve pending memberships belonging to the guild owner
        if ($isOwner) {
            $changed = false;
            foreach ($guild->getPending() as $membership) {
                if ($membership->getCharacter()->getUser()->getId() === $currentUser->getId()) {
                    $membership->setStatus(MemberStatus::Member);
                    $changed = true;
                }
            }
            if ($changed) {
                $em->flush();
            }
        }

        $isLeader = false;
        foreach ($guild->getMemberships() as $m) {
            if ($m->getStatus() === MemberStatus::Leader && $m->getCharacter()->getUser()->getId() === $currentUser->getId()) {
                $isLeader = true;
                break;
            }
        }

        $descriptionForm = $this->createForm(GuildDescriptionType::class, $guild, [
            'action' => $this->generateUrl('app_guild_description', ['id' => $guild->getId()]),
        ]);

        $eligible            = $charRepo->findEligibleForGuild($currentUser, $guild->getServer());
        $confirmedCharacters = $charRepo->findConfirmedInGuild($currentUser, $guild);
        $raids               = $raidRepo->findByGuild($guild);

        return $this->render('guild/show.html.twig', [
            'guild'               => $guild,
            'eligible'            => $eligible,
            'confirmedCharacters' => $confirmedCharacters,
            'isOwner'             => $isOwner,
            'isLeader'            => $isLeader,
            'raids'               => $raids,
            'descriptionForm'     => $descriptionForm,
        ]);
    }

    #[Route('/{id}/membres', name: 'app_guild_members')]
    public function members(Guild $guild): Response
    {
        $currentUser = $this->getUser();
        $isOwner     = $guild->getOwner()->getId() === $currentUser->getId();

        $isLeader = false;
        foreach ($guild->getMemberships() as $m) {
            if ($m->getStatus() === MemberStatus::Leader && $m->getCharacter()->getUser()->getId() === $currentUser->getId()) {
                $isLeader = true;
                break;
            }
        }

        return $this->render('guild/members.html.twig', [
            'guild'    => $guild,
            'isOwner'  => $isOwner,
            'isLeader' => $isLeader,
        ]);
    }

    #[Route('/{id}/description', name: 'app_guild_description', methods: ['POST'])]
    public function updateDescription(Guild $guild, Request $request, EntityManagerInterface $em): Response
    {
        $currentUser = $this->getUser();
        $isLeader = false;
        foreach ($guild->getMemberships() as $m) {
            if ($m->getStatus() === MemberStatus::Leader && $m->getCharacter()->getUser()->getId() === $currentUser->getId()) {
                $isLeader = true;
                break;
            }
        }

        if (!$isLeader) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(GuildDescriptionType::class, $guild);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Description mise à jour.');
        }

        return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
    }

    #[Route('/{id}/join', name: 'app_guild_join', methods: ['POST'])]
    public function join(Guild $guild, Request $request, CharacterRepository $charRepo, GuildMembershipRepository $membershipRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('join_guild_' . $guild->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Rechargez la page et réessayez.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        $currentUser = $this->getUser();
        $characterId = (int) $request->request->get('character_id');
        $character   = $characterId > 0 ? $charRepo->find($characterId) : null;

        if (!$character || (int) $character->getUser()->getId() !== (int) $currentUser->getId()) {
            $this->addFlash('error', 'Personnage invalide.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        if ((int) $character->getServer()->getId() !== (int) $guild->getServer()->getId()) {
            $this->addFlash('error', 'Ce personnage n\'est pas sur le même serveur que la guilde.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        if ($membershipRepo->findOneBy(['character' => $character]) !== null) {
            $this->addFlash('error', 'Ce personnage est déjà dans une guilde.');
            return $this->redirectToRoute('app_guild_show', ['id' => $guild->getId()]);
        }

        $isOwner = (int) $guild->getOwner()->getId() === (int) $currentUser->getId();
        $status  = match(true) {
            $isOwner && !$guild->hasLeader() => MemberStatus::Leader,
            $isOwner                         => MemberStatus::Member,
            default                          => MemberStatus::Pending,
        };

        $membership = (new GuildMembership())->setGuild($guild)->setCharacter($character)->setStatus($status);
        $em->persist($membership);
        $em->flush();

        $msg = match($status) {
            MemberStatus::Leader  => $character->getName() . ' est maintenant meneur de ' . $guild->getName() . ' !',
            MemberStatus::Member  => $character->getName() . ' a rejoint ' . $guild->getName() . ' !',
            MemberStatus::Pending => $character->getName() . ' a demandé à rejoindre ' . $guild->getName() . '. En attente de validation.',
        };

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

        if (!$this->isLeaderOf($guild)) {
            throw $this->createAccessDeniedException();
        }

        $membership->setStatus(MemberStatus::Member);
        $em->flush();

        $this->addFlash('success', $membership->getCharacter()->getName() . ' est maintenant membre.');
        return $this->redirectToRoute('app_guild_members', ['id' => $guild->getId()]);
    }

    #[Route('/memberships/{id}/reject', name: 'app_guild_reject', methods: ['POST'])]
    public function reject(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $guild = $membership->getGuild();

        if (!$this->isCsrfTokenValid('membership_' . $membership->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isLeaderOf($guild)) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() === MemberStatus::Leader) {
            $this->addFlash('error', 'Le meneur ne peut pas être exclu.');
            return $this->redirectToRoute('app_guild_members', ['id' => $guild->getId()]);
        }

        $name = $membership->getCharacter()->getName();
        $em->remove($membership);
        $em->flush();

        $this->addFlash('success', $name . ' a été retiré de la guilde.');
        return $this->redirectToRoute('app_guild_members', ['id' => $guild->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_guild_delete', methods: ['POST'])]
    public function delete(Guild $guild, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_guild_' . $guild->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isLeaderOf($guild)) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($guild);
        $em->flush();

        $this->addFlash('success', 'La guilde a été supprimée.');
        return $this->redirectToRoute('app_guild_index');
    }

    private function isLeaderOf(Guild $guild): bool
    {
        $userId = (int) $this->getUser()->getId();
        foreach ($guild->getMemberships() as $m) {
            if ($m->getStatus() === MemberStatus::Leader && (int) $m->getCharacter()->getUser()->getId() === $userId) {
                return true;
            }
        }
        return false;
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
