<?php

namespace App\Entity;

use App\Repository\RaidRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaidRepository::class)]
class Raid
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private RaidTemplate $raidTemplate;

    #[ORM\ManyToOne(inversedBy: 'raids')]
    #[ORM\JoinColumn(nullable: false)]
    private Guild $guild;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Character $creator;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: RaidStatus::class, length: 10)]
    private RaidStatus $status = RaidStatus::Open;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: RaidParticipant::class, mappedBy: 'raid', cascade: ['remove'])]
    private Collection $participants;

    #[ORM\OneToMany(targetEntity: Enigme::class, mappedBy: 'raid', cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderNumber' => 'ASC'])]
    private Collection $enigmes;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->enigmes      = new ArrayCollection();
        $this->createdAt    = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRaidTemplate(): RaidTemplate { return $this->raidTemplate; }
    public function setRaidTemplate(RaidTemplate $t): static { $this->raidTemplate = $t; return $this; }

    public function getGuild(): Guild { return $this->guild; }
    public function setGuild(Guild $guild): static { $this->guild = $guild; return $this; }

    public function getCreator(): Character { return $this->creator; }
    public function setCreator(Character $creator): static { $this->creator = $creator; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function getStatus(): RaidStatus { return $this->status; }
    public function setStatus(RaidStatus $status): static { $this->status = $status; return $this; }

    public function getScheduledAt(): ?\DateTimeImmutable { return $this->scheduledAt; }
    public function setScheduledAt(?\DateTimeImmutable $d): static { $this->scheduledAt = $d; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getParticipants(): Collection { return $this->participants; }

    public function getAcceptedParticipants(): Collection
    {
        return $this->participants->filter(
            fn(RaidParticipant $p) => $p->getStatus() === RaidParticipantStatus::Accepted
        );
    }

    public function getPendingParticipants(): Collection
    {
        return $this->participants->filter(
            fn(RaidParticipant $p) => $p->getStatus() === RaidParticipantStatus::Pending
        );
    }

    public function getEnigmes(): Collection { return $this->enigmes; }

    public function isFull(): bool
    {
        return $this->getAcceptedParticipants()->count() >= $this->raidTemplate->getMaxParticipants();
    }

    public function isParticipant(Character $character): bool
    {
        return $this->participants->exists(
            fn($_, RaidParticipant $p) => $p->getCharacter() === $character
        );
    }

    public function __toString(): string
    {
        return $this->raidTemplate->getName() . ' — ' . $this->guild->getName();
    }
}
