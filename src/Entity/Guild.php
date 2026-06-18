<?php

namespace App\Entity;

use App\Repository\GuildRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Character;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GuildRepository::class)]
class Guild
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(
        pattern: '/^[\p{L}0-9 \'\-]+$/u',
        message: 'Le nom ne peut contenir que des lettres, chiffres, espaces, apostrophes et tirets.',
    )]
    private string $name;

    #[ORM\Column(length: 160, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(protocols: ['https'], requireTld: true, message: 'L\'URL du webhook Discord doit être une URL HTTPS valide.')]
    private ?string $discordWebhookUrl = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Server $server;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\OneToMany(targetEntity: GuildMembership::class, mappedBy: 'guild', cascade: ['remove'])]
    private Collection $memberships;

    #[ORM\OneToMany(targetEntity: Raid::class, mappedBy: 'guild', cascade: ['remove'])]
    private Collection $raids;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
        $this->raids       = new ArrayCollection();
        $this->createdAt   = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static
    {
        $this->name = $name;
        if (empty($this->slug)) {
            $slug = (new AsciiSlugger('fr'))->slug($name)->lower()->toString();
            $this->slug = $slug !== '' ? $slug : 'guild-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        return $this;
    }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): static { $this->imagePath = $imagePath; return $this; }

    public function getDiscordWebhookUrl(): ?string { return $this->discordWebhookUrl; }
    public function setDiscordWebhookUrl(?string $url): static { $this->discordWebhookUrl = $url; return $this; }

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

    public function getLeaderCharacter(): ?Character
    {
        foreach ($this->memberships as $m) {
            if ($m->getStatus() === MemberStatus::Leader) {
                return $m->getCharacter();
            }
        }
        return null;
    }

    public function hasLeader(): bool
    {
        return $this->memberships->exists(
            fn($_, GuildMembership $m) => $m->getStatus() === MemberStatus::Leader
        );
    }

    public function isLeaderOf(User $user): bool
    {
        foreach ($this->memberships as $m) {
            if ($m->getStatus() === MemberStatus::Leader
                && $m->getCharacter()->getUser()->getId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    public function getRaids(): Collection { return $this->raids; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function __toString(): string { return $this->name; }
}
