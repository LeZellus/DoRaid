<?php

namespace App\Controller;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Form\GuildEditType;
use App\Form\GuildType;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use App\Security\GuildVoter;
use App\Service\FileUploadService;
use App\Service\GuildService;
use App\Traits\BusinessRuleFlashTrait;
use App\Traits\CharacterOwnershipTrait;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/guildes')]
class GuildController extends AbstractController
{
    use BusinessRuleFlashTrait;
    use CharacterOwnershipTrait;
    use CsrfGuardTrait;

    public function __construct(
        private readonly CharacterRepository $charRepo,
        private readonly GuildService $guildService,
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
    public function new(Request $request): Response
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

            if (!$character || !$character->belongsTo($user)) {
                $this->addFlash('error', 'Sélectionnez un personnage meneur pour cette guilde.');
                return $this->render('guild/new.html.twig', [
                    'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => $characterId,
                ]);
            }

            if ($this->handleBusinessRule(
                fn() => $this->guildService->createGuild($guild, $character, $user),
                $character->getName() . ' est maintenant meneur de ' . $guild->getName() . ' !'
            )) {
                return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
            }

            return $this->render('guild/new.html.twig', [
                'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => $characterId,
            ]);
        }

        return $this->render('guild/new.html.twig', [
            'form' => $form, 'charsByServerId' => $charsByServerId, 'submittedCharId' => 0,
        ]);
    }

    #[Route('/{slug}', name: 'app_guild_show')]
    public function show(string $slug, GuildRepository $guildRepo): Response
    {
        $guild = $guildRepo->findBySlugWithMembers($slug);
        if (!$guild) {
            throw $this->createNotFoundException('Guilde introuvable.');
        }

        $data = $this->guildService->buildShowData($guild, $this->getUser());

        return $this->render('guild/show.html.twig', ['guild' => $guild] + $data);
    }

    #[Route('/{slug}/membres', name: 'app_guild_members')]
    public function members(string $slug, GuildRepository $guildRepo): Response
    {
        $guild = $guildRepo->findBySlugWithMembers($slug);
        if (!$guild) {
            throw $this->createNotFoundException('Guilde introuvable.');
        }

        $currentUser = $this->getUser();

        return $this->render('guild/members.html.twig', [
            'guild'    => $guild,
            'isOwner'  => $guild->isOwnedBy($currentUser),
            'isLeader' => $currentUser && $guild->isLeaderOf($currentUser),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/rejoindre', name: 'app_guild_join', methods: ['POST'])]
    public function join(#[MapEntity(mapping: ['slug' => 'slug'])] Guild $guild, Request $request, RateLimiterFactoryInterface $joinGuildLimiter): Response
    {
        $this->requireCsrfToken('join_guild_' . $guild->getId(), $request);

        $currentUser = $this->getUser();

        $limiter = $joinGuildLimiter->create($currentUser->getUserIdentifier());
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de demandes d\'adhésion en peu de temps. Réessayez dans quelques instants.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }
        $character = $this->resolveOwnCharacter($request, $this->charRepo);

        if (!$character) {
            $this->addFlash('error', 'Personnage invalide.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        $this->handleBusinessRule(
            fn() => $this->guildService->joinGuild($guild, $character, $currentUser),
            fn(MemberStatus $status) => match($status) {
                MemberStatus::Leader  => $character->getName() . ' est maintenant meneur de ' . $guild->getName() . ' !',
                MemberStatus::Member  => $character->getName() . ' a rejoint ' . $guild->getName() . ' !',
                MemberStatus::Pending => $character->getName() . ' a demandé à rejoindre ' . $guild->getName() . '. En attente de validation.',
            }
        );

        return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/approuver', name: 'app_guild_approve', methods: ['POST'])]
    public function approve(GuildMembership $membership, Request $request): Response
    {
        $guild = $membership->getGuild();
        $this->requireCsrfToken('membership_' . $membership->getId(), $request);
        $this->denyAccessUnlessGranted(GuildVoter::LEADER, $guild);

        $this->handleBusinessRule(
            fn() => $this->guildService->approveMembership($membership),
            $membership->getCharacter()->getName() . ' est maintenant membre.'
        );

        return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/refuser', name: 'app_guild_reject', methods: ['POST'])]
    public function reject(GuildMembership $membership, Request $request): Response
    {
        $guild = $membership->getGuild();
        $this->requireCsrfToken('membership_' . $membership->getId(), $request);
        $this->denyAccessUnlessGranted(GuildVoter::LEADER, $guild);

        $name = $membership->getCharacter()->getName();
        $this->handleBusinessRule(
            fn() => $this->guildService->rejectMembership($membership),
            $name . ' a été retiré de la guilde.'
        );

        return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/{action}', name: 'app_guild_raid_permission', methods: ['POST'], requirements: ['action' => 'autoriser-raids|revoquer-raids'])]
    public function setRaidCreatorPermission(GuildMembership $membership, string $action, Request $request): Response
    {
        $guild = $membership->getGuild();
        $this->requireCsrfToken('raid_creator_' . $membership->getId(), $request);
        $this->denyAccessUnlessGranted(GuildVoter::LEADER, $guild);

        $grant = $action === 'autoriser-raids';
        $this->handleBusinessRule(
            fn() => $this->guildService->setCanCreateRaids($membership, $grant),
            $membership->getCharacter()->getName() . ($grant ? ' peut maintenant créer des raids.' : ' ne peut plus créer de raids.')
        );

        return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/transferer-meneur', name: 'app_guild_transfer_leader', methods: ['POST'])]
    public function transferLeader(GuildMembership $to, Request $request): Response
    {
        $guild = $to->getGuild();
        $this->requireCsrfToken('transfer_leader_' . $to->getId(), $request);
        $this->denyAccessUnlessGranted(GuildVoter::LEADER, $guild);

        $from = null;
        foreach ($guild->getMemberships() as $m) {
            if ($m->getStatus() === MemberStatus::Leader && $m->belongsTo($this->getUser())) {
                $from = $m;
                break;
            }
        }

        if ($from === null) {
            throw $this->createAccessDeniedException();
        }

        $this->handleBusinessRule(
            fn() => $this->guildService->transferLeadership($from, $to),
            $to->getCharacter()->getName() . ' est maintenant meneur de ' . $guild->getName() . '.'
        );

        return $this->redirectToRoute('app_guild_members', ['slug' => $guild->getSlug()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/membres/{id}/quitter', name: 'app_guild_leave', methods: ['POST'])]
    public function leave(GuildMembership $membership, Request $request): Response
    {
        $this->requireCsrfToken('leave_' . $membership->getId(), $request);

        if (!$membership->belongsTo($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $guildSlug = $membership->getGuild()->getSlug();

        if (!$this->handleBusinessRule(fn() => $this->guildService->leaveGuild($membership), 'Vous avez quitté la guilde.')) {
            return $this->redirectToRoute('app_guild_show', ['slug' => $guildSlug]);
        }

        $referer = $request->headers->get('referer');
        $safeReferer = $referer && str_starts_with($referer, $request->getSchemeAndHttpHost() . '/') ? $referer : null;
        return $safeReferer ? $this->redirect($safeReferer) : $this->redirectToRoute('app_guild_show', ['slug' => $guildSlug]);
    }

    #[IsGranted('GUILD_LEADER', subject: 'guild')]
    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/supprimer', name: 'app_guild_delete', methods: ['POST'])]
    public function delete(#[MapEntity(mapping: ['slug' => 'slug'])] Guild $guild, Request $request): Response
    {
        $this->requireCsrfToken('delete_guild_' . $guild->getId(), $request);
        $this->guildService->deleteGuild($guild);

        $this->addFlash('success', 'La guilde a été supprimée.');
        return $this->redirectToRoute('app_guild_index');
    }

    #[IsGranted('GUILD_LEADER', subject: 'guild')]
    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/modifier', name: 'app_guild_edit', methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])] Guild $guild,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        $form = $this->createForm(GuildEditType::class, $guild);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadDir = $projectDir . '/public/uploads/guildes/' . $guild->getSlug();
                $guild->setImagePath($this->fileUpload->replace($uploadDir, $guild->getImagePath(), $imageFile, uniqid('cover-', true)));
            }

            $em->flush();
            $this->addFlash('success', 'Guilde mise à jour.');
            return $this->redirectToRoute('app_guild_show', ['slug' => $guild->getSlug()]);
        }

        return $this->render('guild/edit.html.twig', ['guild' => $guild, 'form' => $form]);
    }
}
