<?php

namespace App\Entity;

use App\Repository\RaidParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaidParticipantRepository::class)]
#[ORM\UniqueConstraint(columns: ['raid_id', 'character_id'])]
class RaidParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Raid $raid;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', enumType: RaidParticipantStatus::class, length: 10)]
    private RaidParticipantStatus $status = RaidParticipantStatus::Pending;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RaidGroup $group = null;

    /**
     * Modificateurs d'initiative temporaires (brandades, Dofus Cauchemar) — valeurs de
     * InitiativeModifier, propres à ce raid. Ne modifient jamais Character::$initiative.
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $initiativeModifiers = [];

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRaid(): Raid { return $this->raid; }
    public function setRaid(Raid $raid): static { $this->raid = $raid; return $this; }

    public function getCharacter(): Character { return $this->character; }
    public function setCharacter(Character $character): static { $this->character = $character; return $this; }

    public function getStatus(): RaidParticipantStatus { return $this->status; }
    public function setStatus(RaidParticipantStatus|string $status): static
    {
        $this->status = is_string($status) ? RaidParticipantStatus::from($status) : $status;
        return $this;
    }

    // Méthodes utilisées exclusivement par le composant Workflow (attend/retourne des strings)
    public function getWorkflowStatus(): string { return $this->status->value; }
    public function setWorkflowStatus(string $status): static { return $this->setStatus($status); }

    public function getGroup(): ?RaidGroup { return $this->group; }
    public function setGroup(?RaidGroup $group): static { $this->group = $group; return $this; }

    /** @return InitiativeModifier[] */
    public function getInitiativeModifiers(): array
    {
        return array_map(fn(string $value) => InitiativeModifier::from($value), $this->initiativeModifiers);
    }

    public function hasInitiativeModifier(InitiativeModifier $modifier): bool
    {
        return in_array($modifier->value, $this->initiativeModifiers, true);
    }

    public function toggleInitiativeModifier(InitiativeModifier $modifier): static
    {
        if ($this->hasInitiativeModifier($modifier)) {
            $this->initiativeModifiers = array_values(array_diff($this->initiativeModifiers, [$modifier->value]));
        } else {
            $this->initiativeModifiers[] = $modifier->value;
        }
        return $this;
    }

    public function getInitiativeModifierTotal(): int
    {
        return array_sum(array_map(
            fn(InitiativeModifier $m) => $m->points(),
            $this->getInitiativeModifiers()
        ));
    }

    /** Initiative de base + modificateurs actifs pour ce raid ; null si rien n'est renseigné/actif. */
    public function getEffectiveInitiative(): ?int
    {
        $total = $this->getInitiativeModifierTotal();
        if ($this->character->getInitiative() === null && $total === 0) {
            return null;
        }
        return ($this->character->getInitiative() ?? 0) + $total;
    }

    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }

    public function isGuildMember(): bool
    {
        return $this->character->isConfirmedMemberOf($this->raid->getGuild());
    }
}
