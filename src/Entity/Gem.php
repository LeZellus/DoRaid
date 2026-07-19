<?php

namespace App\Entity;

use App\Repository\GemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GemRepository::class)]
class Gem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column]
    #[Assert\Positive]
    private int $value;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getValue(): int { return $this->value; }
    public function setValue(int $value): static { $this->value = $value; return $this; }

    public function __toString(): string { return $this->name . ' (' . $this->value . ')'; }
}
