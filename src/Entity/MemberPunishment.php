<?php

namespace App\Entity;

use App\Repository\MemberPunishmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberPunishmentRepository::class)]
class MemberPunishment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'punishments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GuildMembership $membership;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** null = punition à durée illimitée */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getMembership(): GuildMembership { return $this->membership; }
    public function setMembership(GuildMembership $membership): static { $this->membership = $membership; return $this; }

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }

    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = trim($reason); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function isPermanent(): bool { return $this->expiresAt === null; }

    public function isActive(): bool { return $this->expiresAt === null || $this->expiresAt > new \DateTimeImmutable(); }

    public function isWrittenBy(User $user): bool { return (int) $this->author->getId() === (int) $user->getId(); }
}
