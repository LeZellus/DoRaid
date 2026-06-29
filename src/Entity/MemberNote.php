<?php

namespace App\Entity;

use App\Repository\MemberNoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberNoteRepository::class)]
class MemberNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GuildMembership $membership;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getMembership(): GuildMembership { return $this->membership; }
    public function setMembership(GuildMembership $membership): static { $this->membership = $membership; return $this; }

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = trim($content); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isWrittenBy(User $user): bool { return (int) $this->author->getId() === (int) $user->getId(); }
}
