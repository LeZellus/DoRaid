<?php

namespace App\Entity;

use App\Repository\SalleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalleRepository::class)]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'salles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RaidTemplate $raidTemplate;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $levelMin;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(propertyPath: 'levelMin')]
    private int $levelMax;

    #[ORM\Column]
    private int $orderNumber = 1;

    #[ORM\OneToMany(targetEntity: SalleComposition::class, mappedBy: 'salle', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderNumber' => 'ASC'])]
    private Collection $compositions;

    #[ORM\OneToMany(targetEntity: SalleImage::class, mappedBy: 'salle', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $images;

    public function __construct()
    {
        $this->compositions = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getRaidTemplate(): RaidTemplate { return $this->raidTemplate; }
    public function setRaidTemplate(RaidTemplate $raidTemplate): static { $this->raidTemplate = $raidTemplate; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getLevelMin(): int { return $this->levelMin; }
    public function setLevelMin(int $levelMin): static { $this->levelMin = $levelMin; return $this; }

    public function getLevelMax(): int { return $this->levelMax; }
    public function setLevelMax(int $levelMax): static { $this->levelMax = $levelMax; return $this; }

    public function getOrderNumber(): int { return $this->orderNumber; }
    public function setOrderNumber(int $orderNumber): static { $this->orderNumber = $orderNumber; return $this; }

    public function getCompositions(): Collection { return $this->compositions; }

    public function getImages(): Collection { return $this->images; }

    public function getLevelRange(): string { return '[' . $this->levelMin . ',' . $this->levelMax . ']'; }

    public function __toString(): string { return $this->name . ' ' . $this->getLevelRange(); }
}
