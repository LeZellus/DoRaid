<?php

namespace App\Entity;

use App\Repository\EnigmeTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnigmeTemplateRepository::class)]
#[ORM\UniqueConstraint(columns: ['raid_template_id', 'order_number'])]
class EnigmeTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RaidTemplate $raidTemplate;

    #[ORM\Column(length: 100)]
    private string $title;

    #[ORM\Column]
    private int $orderNumber;

    public function getId(): ?int { return $this->id; }

    public function getRaidTemplate(): RaidTemplate { return $this->raidTemplate; }
    public function setRaidTemplate(RaidTemplate $t): static { $this->raidTemplate = $t; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getOrderNumber(): int { return $this->orderNumber; }
    public function setOrderNumber(int $n): static { $this->orderNumber = $n; return $this; }
}
