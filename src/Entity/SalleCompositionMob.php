<?php

namespace App\Entity;

use App\Repository\SalleCompositionMobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalleCompositionMobRepository::class)]
#[ORM\UniqueConstraint(columns: ['composition_id', 'mob_id'])]
class SalleCompositionMob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'mobQuantities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalleComposition $composition;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Mob $mob;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity;

    public function getId(): ?int { return $this->id; }

    public function getComposition(): SalleComposition { return $this->composition; }
    public function setComposition(SalleComposition $composition): static { $this->composition = $composition; return $this; }

    public function getMob(): Mob { return $this->mob; }
    public function setMob(Mob $mob): static { $this->mob = $mob; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }
}
