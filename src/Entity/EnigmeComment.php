<?php

namespace App\Entity;

use App\Repository\EnigmeCommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnigmeCommentRepository::class)]
class EnigmeComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Enigme $enigme;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Character $author;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEnigme(): Enigme { return $this->enigme; }
    public function setEnigme(Enigme $enigme): static { $this->enigme = $enigme; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getAuthor(): Character { return $this->author; }
    public function setAuthor(Character $character): static { $this->author = $character; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
