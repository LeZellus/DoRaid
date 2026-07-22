<?php

namespace App\Entity;

use App\Repository\GuildMembershipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GuildMembershipRepository::class)]
class GuildMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Guild $guild;

    #[ORM\OneToOne(inversedBy: 'membership')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', enumType: MemberStatus::class, length: 10)]
    private MemberStatus $status;

    #[ORM\Column(options: ['default' => false])]
    private bool $canCreateRaids = false;

    #[ORM\OneToMany(mappedBy: 'membership', targetEntity: MemberNote::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $notes;

    #[ORM\OneToMany(mappedBy: 'membership', targetEntity: MemberPunishment::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $punishments;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
        $this->notes       = new ArrayCollection();
        $this->punishments = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getGuild(): Guild { return $this->guild; }
    public function setGuild(Guild $guild): static { $this->guild = $guild; return $this; }

    public function getCharacter(): Character { return $this->character; }
    public function setCharacter(Character $character): static { $this->character = $character; return $this; }
    public function belongsTo(User $user): bool { return $this->character->belongsTo($user); }

    public function getStatus(): MemberStatus { return $this->status; }
    public function setStatus(MemberStatus|string $status): static
    {
        $this->status = is_string($status) ? MemberStatus::from($status) : $status;
        return $this;
    }

    // Méthodes utilisées exclusivement par le composant Workflow (attend/retourne des strings)
    public function getWorkflowStatus(): string { return $this->status->value; }
    public function setWorkflowStatus(string $status): static { return $this->setStatus($status); }

    public function canCreateRaids(): bool { return $this->status === MemberStatus::Leader || $this->canCreateRaids; }
    public function setCanCreateRaids(bool $canCreateRaids): static { $this->canCreateRaids = $canCreateRaids; return $this; }

    /** @return Collection<int, MemberNote> */
    public function getNotes(): Collection { return $this->notes; }

    /** @return Collection<int, MemberPunishment> */
    public function getPunishments(): Collection { return $this->punishments; }

    /**
     * Punition en cours la plus sévère : une punition à durée illimitée prime sur
     * toute punition temporaire ; sinon celle dont la date de fin est la plus lointaine.
     */
    public function getActivePunishment(): ?MemberPunishment
    {
        $active = $this->punishments->filter(fn(MemberPunishment $p) => $p->isActive());
        if ($active->isEmpty()) {
            return null;
        }

        $latest = null;
        foreach ($active as $punishment) {
            if ($punishment->isPermanent()) {
                return $punishment;
            }
            if ($latest === null || $punishment->getExpiresAt() > $latest->getExpiresAt()) {
                $latest = $punishment;
            }
        }
        return $latest;
    }

    public function isPunished(): bool { return $this->getActivePunishment() !== null; }

    public function getRequestedAt(): \DateTimeImmutable { return $this->requestedAt; }
}
