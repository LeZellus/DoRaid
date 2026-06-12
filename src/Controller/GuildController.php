<?php

namespace App\Controller;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Entity\RaidStatus;
use App\Form\GuildEditType;
use App\Form\GuildType;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidRepository;
use App\Service\FileUploadService;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/guildes')]
class GuildController extends AbstractController
{
    use CsrfGuardTrait;

    public function __construct(
        private readonly CharacterRepository $charRepo,
        private readonly FileUploadService $fileUpload,
    ) {}

    #[Route('', name: 'app_guild_index')]
    public function index(GuildRepository $repo, GuildMembershipRepository $membershipRepo): Response
    {
        $guilds   = $repo->findAllOrdered();
        $byServer = [];
        foreach ($guilds as $guild) {
            $byServer[$guild->getServer()->getName()][] = $guild;
        }

        $leaderGuildIds = $this->getUser()
            ? $membershipRepo->findLeaderGuildIdsForUser($this->getUser())
            : [];

        return $this->render('guild/index.html.twig', [
            'byServer'       => $byServer,
            'leaderGuildIds' => $leaderGuildIds,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/creer', name: 'app_guild_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, GuildRepository $repo): Response
    {
        $user       = $this->getUser();
        $allChars   = $this->charRepo->findByUser($user);
        $characters = array_values(array_filter($allChars, fn($c) => $c->getMembership() === null));

        if (empty($allChars)) {
            $this->addFlash('error', 'Vous devez d\'abord créer un personnage avant de pouvoir fonder une guilde.');
            return $this->redirectToRoute('app_character_new');
        }

        if (empty($characters)) {
            $this->addFlash('error', 'Tous vos personnages sont déjà membres d\'une guilde. Créez un nouveau personnage ou quittez une guilde pour en fonder une.');
            return $this->redirectToRoute('app_guild_index');
        }

        $charsByServerId = [];
        foreach ($characters as $c) {
            $charsByServerId[$c->getServer()->getId()][] = $c;
        }

        $guild = new Guild();
        $form  = $this->createForm(GuildType::class, $guild);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $characterId = (int) $request->request->get('character_id');
            $character   = $characterId > 0 ? $this->charRepo->find($characterId) : null;

            if (!$character || (int) $character->getUser()->getId() !== (int) $user->getId()) {
                $this->addFlash('error', 'Sélectionnez un personnage meneur pour cette guilde.');
                return $this->render('guild/new.html.twig', [
                    'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => $characterId,
                ]);
            }

            if ($character->getMembership() !== null) {
                $this->addFlash('error', 'Ce personnage est déjà dans une guilde.');
                return $this->render('guild/new.html.twig', [
                    'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => $characterId,
                ]);
            }

            if ((int) $character->getServer()->getId() !== (int) $guild->getServer()->getId()) {
                $this->addFlash('error', 'Ce personnage n\'est pas sur le serveur sélectionné pour la guilde.');
                return $this->render('guild/new.html.twig', [
                    'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => $characterId,
                ]);
            }

            $guild->setOwner($user);
            $guild->setSlug($this->uniqueSlug($guild->getName(), $repo));
            $em->persist($guild);
            $em->persist(
                (new GuildMembership())->setGuild($guild)->setCharacter($character)->setStatus(MemberStatus::Leader)
            );
            $em->flush();

            $this->addFlash('success', $character->getName() . ' est maintenant meneur de ' . $guild->getName() . ' !');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        return $this->render('guild/new.html.twig', [
            'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => 0,
        ]);
    }

    #[Route('/{slug}', name: 'app_guild_show')]
    public function show(Guild $guild, RaidRepository $raidRepo, EntityManagerInterface $em): Response
    {
        $currentUser = $this->getUser();
        $isOwner     = $currentUser && $guild->getOwner()->getId() === $currentUser->getId();

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

        $isLeader            = $currentUser && $guild->isLeaderOf($currentUser);
        $eligible            = $currentUser ? $this->charRepo->findEligibleForGuild($currentUser, $guild->getServer()) : [];
        $confirmedCharacters = $currentUser ? $this->charRepo->findConfirmedInGuild($currentUser, $guild) : [];
        $isMember            = $isLeader || !empty($confirmedCharacters);

        $allRaids    = array_filter($raidRepo->findByGuild($guild), fn($r) => $r->isPublic() || $isMember);
        $activeRaids = array_values(array_filter($allRaids, fn($r) => $r->getStatus() === RaidStatus::Open));
        $closedRaids = array_values(array_filter($allRaids, fn($r) => $r->getStatus() === RaidStatus::Closed));

        return $this->render('guild/show.html.twig', [
            'guild'               => $guild,
            'eligible'            => $eligible,
            'confirmedCharacters' => $confirmedCharacters,
            'isOwner'             => $isOwner,
            'isLeader'            => $isLeader,
            'activeRaids'         => $activeRaids,
            'closedRaids'         => $closedRaids,
        ]);
    }

    #[Route('/{slug}/membres', name: 'app_guild_members')]
    public function members(Guild $guild): Response
    {
        $currentUser = $this->getUser();

        return $this->render('guild/members.html.twig', [
            'guild'    => $guild,
            'isOwner'  => $currentUser && $guild->getOwner()->getId() === $currentUser->getId(),
            'isLeader' => $currentUser && $guild->isLeaderOf($currentUser),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/rejoindre', name: 'app_guild_join', methods: ['POST'])]
    public function join(Guild $guild, Request $request, GuildMembershipRepository $membershipRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('join_guild_' . $guild->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Rechargez la page et réessayez.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        $currentUser = $this->getUser();
        $characterId = (int) $request->request->get('character_id');
        $character   = $characterId > 0 ? $this->charRepo->find($characterId) : null;

        if (!$character || (int) $character->getUser()->getId() !== (int) $currentUser->getId()) {
            $this->addFlash('error', 'Personnage invalide.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        if ((int) $character->getServer()->getId() !== (int) $guild->getServer()->getId()) {
            $this->addFlash('error', 'Ce personnage n\'est pas sur le même serveur que la guilde.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        if ($membershipRepo->findOneBy(['character' => $character]) !== null) {
            $this->addFlash('error', 'Ce personnage est déjà dans une guilde.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        $isOwner = (int) $guild->getOwner()->getId() === (int) $currentUser->getId();
        $status  = match(true) {
            $isOwner && !$guild->hasLeader() => MemberStatus::Leader,
            $isOwner                         => MemberStatus::Member,
            default                          => MemberStatus::Pending,
        };

        $em->persist((new GuildMembership())->setGuild($guild)->setCharacter($character)->setStatus($status));
        $em->flush();

        $this->addFlash('success', match($status) {
            MemberStatus::Leader  => $character->getName() . ' est maintenant meneur de ' . $guild->getName() . ' !',
            MemberStatus::Member  => $character->getName() . ' a rejoint ' . $guild->getName() . ' !',
            MemberStatus::Pending => $character->getName() . ' a demandé à rejoindre ' . $guild->getName() . '. En attente de validation.',
        });

        return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/approuver', name: 'app_guild_approve', methods: ['POST'])]
    public function approve(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $guild = $membership->getGuild();
        $this->requireCsrfToken('membership_' . $membership->getId(), $request);

        if (!$guild->isLeaderOf($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $membership->setStatus(MemberStatus::Member);
        $em->flush();

        $this->addFlash('success', $membership->getCharacter()->getName() . ' est maintenant membre.');
        return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/refuser', name: 'app_guild_reject', methods: ['POST'])]
    public function reject(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $guild = $membership->getGuild();
        $this->requireCsrfToken('membership_' . $membership->getId(), $request);

        if (!$guild->isLeaderOf($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() === MemberStatus::Leader) {
            $this->addFlash('error', 'Le meneur ne peut pas être exclu.');
            return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
        }

        $name = $membership->getCharacter()->getName();
        $em->remove($membership);
        $em->flush();

        $this->addFlash('success', $name . ' a été retiré de la guilde.');
        return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/quitter', name: 'app_guild_leave', methods: ['POST'])]
    public function leave(GuildMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireCsrfToken('leave_' . $membership->getId(), $request);

        if ($membership->getCharacter()->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() === MemberStatus::Leader) {
            $this->addFlash('error', 'Le meneur ne peut pas quitter la guilde.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $membership->getGuild()->getSlug()]);
        }

        $guildSlug = $membership->getGuild()->getSlug();
        $em->remove($membership);
        $em->flush();

        $this->addFlash('success', 'Vous avez quitté la guilde.');

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_guild_show', ['slug' => $guildSlug]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/supprimer', name: 'app_guild_delete', methods: ['POST'])]
    public function delete(Guild $guild, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireCsrfToken('delete_guild_' . $guild->getId(), $request);

        if (!$guild->isLeaderOf($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($guild);
        $em->flush();

        $this->addFlash('success', 'La guilde a été supprimée.');
        return $this->redirectToRoute('app_guild_index');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/modifier', name: 'app_guild_edit', methods: ['GET', 'POST'])]
    public function edit(
        Guild $guild,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        if (!$guild->isLeaderOf($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(GuildEditType::class, $guild);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadDir = $projectDir . '/public/uploads/guildes/' . $guild->getSlug();
                if ($guild->getImagePath() && file_exists($uploadDir . '/' . $guild->getImagePath())) {
                    unlink($uploadDir . '/' . $guild->getImagePath());
                }
                $guild->setImagePath($this->fileUpload->upload($imageFile, $uploadDir, 'cover'));
            }

            $em->flush();
            $this->addFlash('success', 'Guilde mise à jour.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        return $this->render('guild/edit.html.twig', ['guild' => $guild, 'form' => $form]);
    }

    private function uniqueSlug(string $name, GuildRepository $repo, ?int $excludeId = null): string
    {
        $base = (new \Symfony\Component\String\Slugger\AsciiSlugger('fr'))->slug($name)->lower()->toString();
        $slug = $base;
        $i    = 2;
        while (true) {
            $existing = $repo->findOneBy(['slug' => $slug]);
            if ($existing === null || $existing->getId() === $excludeId) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }
}
