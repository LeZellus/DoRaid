<?php

namespace App\Entity;

use App\Repository\EnigmeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnigmeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_enigme_raid_order', columns: ['raid_id', 'order_number'])]
class Enigme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'enigmes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Raid $raid;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private EnigmeTemplate $sourceTemplate;

    #[ORM\Column]
    private int $orderNumber;

    #[ORM\Column]
    private bool $resolved = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: EnigmeImage::class, mappedBy: 'enigme', cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $images;

    #[ORM\OneToMany(targetEntity: EnigmeComment::class, mappedBy: 'enigme', cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->images    = new ArrayCollection();
        $this->comments  = new ArrayCollection();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRaid(): Raid { return $this->raid; }
    public function setRaid(Raid $raid): static { $this->raid = $raid; return $this; }

    public function getSourceTemplate(): EnigmeTemplate { return $this->sourceTemplate; }
    public function setSourceTemplate(EnigmeTemplate $t): static { $this->sourceTemplate = $t; return $this; }

    public function getTitle(): string { return $this->sourceTemplate->getTitle(); }

    public function getOrderNumber(): int { return $this->orderNumber; }
    public function setOrderNumber(int $n): static { $this->orderNumber = $n; return $this; }

    public function isResolved(): bool { return $this->resolved; }
    public function setResolved(bool $resolved): static { $this->resolved = $resolved; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getImages(): Collection { return $this->images; }
    public function getComments(): Collection { return $this->comments; }

    public function __toString(): string { return $this->sourceTemplate->getTitle(); }
}
