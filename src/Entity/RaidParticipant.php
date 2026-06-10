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
    public function setStatus(RaidParticipantStatus $status): static { $this->status = $status; return $this; }

    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }
}
