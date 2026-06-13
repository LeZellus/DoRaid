<?php

namespace App\Service;

use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Entity\User;
use App\Entity\Character;
use App\Exception\BusinessRuleException;
use App\Repository\GuildMembershipRepository;
use App\Repository\GuildRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Workflow\WorkflowInterface;

class GuildService
{
    public function __construct(
        private readonly GuildRepository $guildRepo,
        private readonly GuildMembershipRepository $membershipRepo,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'state_machine.guild_membership_status')]
        private readonly WorkflowInterface $membershipWorkflow,
    ) {}

    /**
     * Crée une guilde avec le personnage donné comme meneur.
     * Précondition : le contrôleur a vérifié que $character appartient à $user.
     */
    public function createGuild(Guild $guild, Character $character, User $user): void
    {
        if ($character->getMembership() !== null) {
            throw new BusinessRuleException('Ce personnage est déjà dans une guilde.');
        }

        if ((int) $character->getServer()->getId() !== (int) $guild->getServer()->getId()) {
            throw new BusinessRuleException('Ce personnage n\'est pas sur le serveur sélectionné pour la guilde.');
        }

        $guild->setOwner($user);
        $guild->setSlug($this->uniqueSlug($guild->getName()));

        $this->em->persist($guild);
        $this->em->persist(
            (new GuildMembership())->setGuild($guild)->setCharacter($character)->setStatus(MemberStatus::Leader)
        );
        $this->em->flush();
    }

    /**
     * Ajoute un personnage à une guilde.
     * Précondition : le contrôleur a vérifié que $character appartient à $user.
     *
     * @return MemberStatus le statut attribué
     */
    public function joinGuild(Guild $guild, Character $character, User $user): MemberStatus
    {
        if ((int) $character->getServer()->getId() !== (int) $guild->getServer()->getId()) {
            throw new BusinessRuleException('Ce personnage n\'est pas sur le même serveur que la guilde.');
        }

        if ($this->membershipRepo->findOneBy(['character' => $character]) !== null) {
            throw new BusinessRuleException('Ce personnage est déjà dans une guilde.');
        }

        $isOwner = (int) $guild->getOwner()->getId() === (int) $user->getId();
        $status  = match(true) {
            $isOwner && !$guild->hasLeader() => MemberStatus::Leader,
            $isOwner                         => MemberStatus::Member,
            default                          => MemberStatus::Pending,
        };

        $this->em->persist((new GuildMembership())->setGuild($guild)->setCharacter($character)->setStatus($status));
        $this->em->flush();

        return $status;
    }

    /**
     * Retire un personnage de sa guilde.
     * Précondition : le contrôleur a vérifié que $membership appartient à l'utilisateur courant.
     */
    public function leaveGuild(GuildMembership $membership): void
    {
        if ($membership->getStatus() === MemberStatus::Leader) {
            throw new BusinessRuleException('Le meneur ne peut pas quitter la guilde. Transférez d\'abord le rôle de meneur.');
        }

        $this->em->remove($membership);
        $this->em->flush();
    }

    public function approveMembership(GuildMembership $membership): void
    {
        try {
            $this->membershipWorkflow->apply($membership, 'approve');
        } catch (\Symfony\Component\Workflow\Exception\NotEnabledTransitionException) {
            throw new BusinessRuleException('Cette demande ne peut pas être approuvée dans son état actuel.');
        }
        $this->em->flush();
    }

    public function rejectMembership(GuildMembership $membership): void
    {
        if ($membership->getStatus() === MemberStatus::Leader) {
            throw new BusinessRuleException('Le meneur ne peut pas être exclu.');
        }

        $this->em->remove($membership);
        $this->em->flush();
    }

    public function deleteGuild(Guild $guild): void
    {
        $this->em->remove($guild);
        $this->em->flush();
    }

    private function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = (new AsciiSlugger('fr'))->slug($name)->lower()->toString();
        $slug = $base;
        $i    = 2;
        while (true) {
            $existing = $this->guildRepo->findOneBy(['slug' => $slug]);
            if ($existing === null || $existing->getId() === $excludeId) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }
}
