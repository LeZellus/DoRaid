<?php

namespace App\Entity;

use App\Repository\SalleImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalleImageRepository::class)]
class SalleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Salle $salle;

    #[ORM\Column(length: 255)]
    private string $filePath;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSalle(): Salle { return $this->salle; }
    public function setSalle(Salle $salle): static { $this->salle = $salle; return $this; }

    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
