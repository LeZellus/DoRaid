<?php

namespace App\Entity;

use App\Repository\SalleCompositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalleCompositionRepository::class)]
class SalleComposition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'compositions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Salle $salle;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private int $orderNumber = 1;

    #[ORM\OneToMany(targetEntity: SalleCompositionMob::class, mappedBy: 'composition', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $mobQuantities;

    public function __construct()
    {
        $this->mobQuantities = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSalle(): Salle { return $this->salle; }
    public function setSalle(Salle $salle): static { $this->salle = $salle; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }

    public function getOrderNumber(): int { return $this->orderNumber; }
    public function setOrderNumber(int $orderNumber): static { $this->orderNumber = $orderNumber; return $this; }

    public function getMobQuantities(): Collection { return $this->mobQuantities; }

    /** Score de loot espéré par joueur pour cette composition : Σ(quantité × espérance du mob). */
    public function getExpectedScore(): float
    {
        $score = 0.0;
        foreach ($this->mobQuantities as $mobQuantity) {
            $score += $mobQuantity->getQuantity() * $mobQuantity->getMob()->getExpectedPoints();
        }

        return $score;
    }

    public function __toString(): string
    {
        return $this->salle->getName() . ' — ' . ($this->label ?? ('Composition ' . $this->orderNumber));
    }
}
