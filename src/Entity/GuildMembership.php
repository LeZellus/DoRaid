<?php

namespace App\Entity;

use App\Repository\GuildMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GuildMembershipRepository::class)]
class GuildMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Guild $guild;

    #[ORM\OneToOne(inversedBy: 'membership')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', enumType: MemberStatus::class, length: 10)]
    private MemberStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getGuild(): Guild { return $this->guild; }
    public function setGuild(Guild $guild): static { $this->guild = $guild; return $this; }

    public function getCharacter(): Character { return $this->character; }
    public function setCharacter(Character $character): static { $this->character = $character; return $this; }

    public function getStatus(): MemberStatus { return $this->status; }
    public function setStatus(MemberStatus $status): static { $this->status = $status; return $this; }

    public function getRequestedAt(): \DateTimeImmutable { return $this->requestedAt; }
}
