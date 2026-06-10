<?php

namespace App\Entity;

use App\Repository\GuildRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GuildRepository::class)]
class Guild
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Server $server;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\OneToMany(targetEntity: GuildMembership::class, mappedBy: 'guild', cascade: ['remove'])]
    private Collection $memberships;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getServer(): Server { return $this->server; }
    public function setServer(Server $server): static { $this->server = $server; return $this; }

    public function getOwner(): User { return $this->owner; }
    public function setOwner(User $owner): static { $this->owner = $owner; return $this; }

    public function getMemberships(): Collection { return $this->memberships; }

    public function getConfirmed(): Collection
    {
        return $this->memberships->filter(
            fn(GuildMembership $m) => $m->getStatus() !== MemberStatus::Pending
        );
    }

    public function getPending(): Collection
    {
        return $this->memberships->filter(
            fn(GuildMembership $m) => $m->getStatus() === MemberStatus::Pending
        );
    }

    public function hasLeader(): bool
    {
        return $this->memberships->exists(
            fn($_, GuildMembership $m) => $m->getStatus() === MemberStatus::Leader
        );
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function __toString(): string { return $this->name; }
}
