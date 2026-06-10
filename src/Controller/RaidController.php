<?php

namespace App\Controller;

use App\Entity\MemberStatus;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidStatus;
use App\Form\RaidType;
use App\Repository\CharacterRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/raids')]
class RaidController extends AbstractController
{
    #[Route('/new', name: 'app_raid_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        GuildRepository $guildRepo,
        CharacterRepository $charRepo,
        RaidTemplateRepository $templateRepo
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
            $em->persist((new RaidParticipant())->setRaid($raid)->setCharacter($character));
            $em->flush();

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
    public function show(Raid $raid, CharacterRepository $charRepo): Response
    {
        $eligible  = $charRepo->findEligibleForRaid($this->getUser(), $raid);
        $isCreator = $raid->getCreator()->getUser() === $this->getUser();

        return $this->render('raid/show.html.twig', [
            'raid'      => $raid,
            'eligible'  => $eligible,
            'isCreator' => $isCreator,
        ]);
    }

    #[Route('/{id}/join', name: 'app_raid_join', methods: ['POST'])]
    public function join(Raid $raid, Request $request, CharacterRepository $charRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('join_raid_' . $raid->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getStatus() !== RaidStatus::Open) {
            $this->addFlash('error', 'Ce raid est terminé.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        if ($raid->isFull()) {
            $this->addFlash('error', 'Ce raid est complet (' . $raid->getRaidTemplate()->getMaxParticipants() . ' participants max).');
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
            $this->addFlash('error', 'Ce personnage participe déjà à ce raid.');
            return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
        }

        $em->persist((new RaidParticipant())->setRaid($raid)->setCharacter($character));
        $em->flush();

        $this->addFlash('success', $character->getName() . ' a rejoint le raid !');
        return $this->redirectToRoute('app_raid_show', ['id' => $raid->getId()]);
    }

    #[Route('/{id}/close', name: 'app_raid_close', methods: ['POST'])]
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

    #[Route('/participants/{id}/kick', name: 'app_raid_kick', methods: ['POST'])]
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

    #[Route('/{id}/delete', name: 'app_raid_delete', methods: ['POST'])]
    public function delete(Raid $raid, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_raid_' . $raid->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($raid->getCreator()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $guildId = $raid->getGuild()->getId();
        $em->remove($raid);
        $em->flush();

        $this->addFlash('success', 'Raid supprimé.');
        return $this->redirectToRoute('app_guild_show', ['id' => $guildId]);
    }
}
