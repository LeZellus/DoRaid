<?php

namespace App\Entity;

use App\Repository\RaidGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaidGroupRepository::class)]
class RaidGroup
{
    public const MAX_MEMBERS = 8;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'groups')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Raid $raid;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private int $position;

    // EXTRA_LAZY : isFull() ne doit pas charger toute la collection en mémoire (elle resterait
    // alors figée à son état d'avant l'assignation pour le reste de la requête — le membre
    // fraîchement assigné n'apparaîtrait plus qu'après un rechargement de page).
    #[ORM\OneToMany(targetEntity: RaidParticipant::class, mappedBy: 'group', fetch: 'EXTRA_LAZY')]
    private Collection $participants;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getRaid(): Raid { return $this->raid; }
    public function setRaid(Raid $raid): static { $this->raid = $raid; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getParticipants(): Collection { return $this->participants; }

    public function isFull(): bool
    {
        return $this->participants->count() >= self::MAX_MEMBERS;
    }
}
