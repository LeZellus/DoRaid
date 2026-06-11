<?php

namespace App\Entity;

use App\Repository\RaidTemplateRepository;
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

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getMinParticipants(): int { return $this->minParticipants; }
    public function setMinParticipants(int $min): static { $this->minParticipants = $min; return $this; }

    public function getMaxParticipants(): int { return $this->maxParticipants; }
    public function setMaxParticipants(int $max): static { $this->maxParticipants = $max; return $this; }

    public function __toString(): string { return $this->name; }
}
