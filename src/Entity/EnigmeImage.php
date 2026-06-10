<?php

namespace App\Entity;

use App\Repository\EnigmeImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnigmeImageRepository::class)]
class EnigmeImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Enigme $enigme;

    #[ORM\Column(length: 255)]
    private string $filePath;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Character $addedBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEnigme(): Enigme { return $this->enigme; }
    public function setEnigme(Enigme $enigme): static { $this->enigme = $enigme; return $this; }

    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getAddedBy(): Character { return $this->addedBy; }
    public function setAddedBy(Character $character): static { $this->addedBy = $character; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
