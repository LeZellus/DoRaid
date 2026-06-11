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
use App\Repository\GuildRepository;
use App\Repository\RaidTemplateRepository;
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
    public function show(Raid $raid, CharacterRepository $charRepo): Response
    {
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
