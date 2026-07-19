<?php

namespace App\Entity;

use App\Repository\MobDropRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MobDropRateRepository::class)]
#[ORM\UniqueConstraint(columns: ['mob_id', 'gem_id'])]
class MobDropRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dropRates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mob $mob;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Gem $gem;

    // Probabilité de drop, en fraction (0.30 = 30%), pas en pourcentage brut.
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Range(min: 0, max: 1)]
    private float $probability;

    public function getId(): ?int { return $this->id; }

    public function getMob(): Mob { return $this->mob; }
    public function setMob(Mob $mob): static { $this->mob = $mob; return $this; }

    public function getGem(): Gem { return $this->gem; }
    public function setGem(Gem $gem): static { $this->gem = $gem; return $this; }

    public function getProbability(): float { return $this->probability; }
    public function setProbability(float $probability): static { $this->probability = $probability; return $this; }
}
