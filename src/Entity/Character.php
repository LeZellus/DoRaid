<?php

namespace App\Entity;

use App\Repository\CharacterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterRepository::class)]
#[ORM\Table(name: 'game_character')]
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

    public function getMembership(): ?GuildMembership { return $this->membership; }
}
