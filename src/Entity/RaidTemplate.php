<?php

namespace App\Entity;

use App\Repository\RaidTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RaidTemplateRepository::class)]
class RaidTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name;

    #[ORM\Column]
    #[Assert\Positive]
    private int $minParticipants;

    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\GreaterThanOrEqual(propertyPath: 'minParticipants', message: 'Le maximum doit être supérieur ou égal au minimum.')]
    private int $maxParticipants;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null; // in minutes

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\OneToMany(targetEntity: Mob::class, mappedBy: 'raidTemplate', cascade: ['remove'])]
    private Collection $mobs;

    #[ORM\OneToMany(targetEntity: Salle::class, mappedBy: 'raidTemplate', cascade: ['remove'])]
    #[ORM\OrderBy(['orderNumber' => 'ASC'])]
    private Collection $salles;

    public function __construct()
    {
        $this->mobs   = new ArrayCollection();
        $this->salles = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getMinParticipants(): int { return $this->minParticipants; }
    public function setMinParticipants(int $min): static { $this->minParticipants = $min; return $this; }

    public function getMaxParticipants(): int { return $this->maxParticipants; }
    public function setMaxParticipants(int $max): static { $this->maxParticipants = $max; return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): static { $this->duration = $duration; return $this; }

    public function getFormattedDuration(): ?string
    {
        if ($this->duration === null) return null;
        $h = intdiv($this->duration, 60);
        $m = $this->duration % 60;
        if ($h === 0) return $m . 'min';
        return $h . 'h' . ($m > 0 ? str_pad($m, 2, '0', STR_PAD_LEFT) : '');
    }

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): static { $this->imagePath = $imagePath; return $this; }

    public function getMobs(): Collection { return $this->mobs; }

    public function getSalles(): Collection { return $this->salles; }

    public function __toString(): string { return $this->name; }
}
