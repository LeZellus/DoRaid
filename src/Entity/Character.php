<?php

namespace App\Entity;

use App\Repository\CharacterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CharacterRepository::class)]
#[ORM\Table(name: 'game_character')]
#[ORM\UniqueConstraint(name: 'uniq_character_name_server', columns: ['name', 'server_id'])]
#[UniqueEntity(fields: ['name', 'server'], message: 'Un personnage nommé "{{ value }}" existe déjà sur ce serveur.')]
class Character
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private GameClass $gameClass;

    #[ORM\ManyToOne(inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private Server $server;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    #[ORM\Column(type: 'string', enumType: OptimizationLevel::class, nullable: true)]
    private ?OptimizationLevel $optimizationLevel = null;

    #[ORM\OneToOne(mappedBy: 'character')]
    private ?GuildMembership $membership = null;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getGameClass(): GameClass { return $this->gameClass; }
    public function setGameClass(GameClass $gameClass): static { $this->gameClass = $gameClass; return $this; }

    public function getServer(): Server { return $this->server; }
    public function setServer(Server $server): static { $this->server = $server; return $this; }

    public function getLevel(): ?int { return $this->level; }
    public function setLevel(?int $level): static { $this->level = $level; return $this; }

    public function getOptimizationLevel(): ?OptimizationLevel { return $this->optimizationLevel; }
    public function setOptimizationLevel(?OptimizationLevel $o): static { $this->optimizationLevel = $o; return $this; }

    public function getMembership(): ?GuildMembership { return $this->membership; }

    public function isConfirmedMemberOf(Guild $guild): bool
    {
        return $this->membership !== null
            && $this->membership->getGuild()->getId() === $guild->getId()
            && $this->membership->getStatus() !== MemberStatus::Pending;
    }
}
