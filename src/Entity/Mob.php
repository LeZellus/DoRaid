<?php

namespace App\Entity;

use App\Repository\MobRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MobRepository::class)]
#[ORM\UniqueConstraint(columns: ['raid_template_id', 'name'])]
class Mob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'mobs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RaidTemplate $raidTemplate;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\OneToMany(targetEntity: MobDropRate::class, mappedBy: 'mob', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $dropRates;

    public function __construct()
    {
        $this->dropRates = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getRaidTemplate(): RaidTemplate { return $this->raidTemplate; }
    public function setRaidTemplate(RaidTemplate $raidTemplate): static { $this->raidTemplate = $raidTemplate; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDropRates(): Collection { return $this->dropRates; }

    /** Espérance de points de loot (roll individuel) : Σ(probabilité de drop × valeur de la gemme). */
    public function getExpectedPoints(): float
    {
        $points = 0.0;
        foreach ($this->dropRates as $dropRate) {
            $points += $dropRate->getProbability() * $dropRate->getGem()->getValue();
        }

        return $points;
    }

    public function __toString(): string { return $this->name; }
}
