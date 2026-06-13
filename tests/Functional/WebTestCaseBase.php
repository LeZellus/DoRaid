<?php

namespace App\Tests\Functional;

use App\Entity\Character;
use App\Entity\GameClass;
use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Entity\Notification;
use App\Entity\OptimizationLevel;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidTemplate;
use App\Entity\Server;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class WebTestCaseBase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    /** Crée (ou recrée) le schéma SQLite une seule fois par classe de test. */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::bootKernel();
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncateAll();
        $this->em->clear();
    }

    // ─── Nettoyage ─────────────────────────────────────────────────────────────

    private function truncateAll(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('PRAGMA foreign_keys = OFF');
        foreach ([
            'notification',
            'enigme_comment', 'enigme_image', 'enigme',
            'raid_comment', 'raid_participant', 'raid',
            'guild_membership', 'game_character', 'guild',
            'user', 'server', 'game_class', 'raid_template', 'enigme_template',
        ] as $table) {
            $conn->executeStatement("DELETE FROM \"{$table}\"");
        }
        $conn->executeStatement('PRAGMA foreign_keys = ON');
    }

    // ─── Factories d'entités ───────────────────────────────────────────────────

    protected function makeUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setUsername(substr(explode('@', $email)[0], 0, 50));
        $this->em->persist($user);
        return $user;
    }

    protected function makeServer(): Server
    {
        $server = (new Server())->setName('Serveur-' . uniqid('', true));
        $this->em->persist($server);
        return $server;
    }

    protected function makeGameClass(): GameClass
    {
        $gc = (new GameClass())->setName('Classe-' . uniqid('', true));
        $this->em->persist($gc);
        return $gc;
    }

    protected function makeCharacter(User $user, Server $server): Character
    {
        $char = (new Character())
            ->setUser($user)
            ->setServer($server)
            ->setGameClass($this->makeGameClass())
            ->setName('Perso-' . uniqid('', true))
            ->setLevel(60)
            ->setOptimizationLevel(OptimizationLevel::High);
        $this->em->persist($char);
        return $char;
    }

    protected function makeGuild(User $owner, Server $server): Guild
    {
        $name  = 'Guilde-' . uniqid('', true);
        $guild = (new Guild())
            ->setName($name)
            ->setSlug('guilde-' . uniqid('', true))
            ->setServer($server)
            ->setOwner($owner);
        $this->em->persist($guild);
        return $guild;
    }

    protected function makeNotification(User $user, string $type = 'participation_pending'): Notification
    {
        $n = (new Notification())
            ->setUser($user)
            ->setType($type)
            ->setTitle('Titre test')
            ->setMessage('Message test');
        $this->em->persist($n);
        return $n;
    }

    protected function makeMembership(Guild $guild, Character $char, MemberStatus $status): GuildMembership
    {
        $m = (new GuildMembership())->setGuild($guild)->setCharacter($char)->setStatus($status);
        $this->em->persist($m);
        $guild->getMemberships()->add($m);
        return $m;
    }

    protected function makeRaidTemplate(string $name): RaidTemplate
    {
        $t = (new RaidTemplate())
            ->setName($name)
            ->setMaxParticipants(5)
            ->setMinParticipants(1)
            ->setDuration(60);
        $this->em->persist($t);
        return $t;
    }

    protected function makeRaid(Guild $guild, Character $creator, bool $isPublic = true, ?string $templateName = null): Raid
    {
        $raid = (new Raid())
            ->setGuild($guild)
            ->setCreator($creator)
            ->setRaidTemplate($this->makeRaidTemplate($templateName ?? 'Template-' . uniqid('', true)))
            ->setIsPublic($isPublic);
        $this->em->persist($raid);
        return $raid;
    }

    protected function makeParticipant(Raid $raid, Character $char, RaidParticipantStatus $status = RaidParticipantStatus::Pending): RaidParticipant
    {
        $p = (new RaidParticipant())->setRaid($raid)->setCharacter($char)->setStatus($status);
        $this->em->persist($p);
        return $p;
    }

    protected function makeRaidComment(Raid $raid, User $author, string $content = 'Commentaire', ?\App\Entity\RaidComment $parent = null): \App\Entity\RaidComment
    {
        $c = (new \App\Entity\RaidComment())->setRaid($raid)->setAuthor($author)->setContent($content);
        if ($parent !== null) {
            $c->setParent($parent);
        }
        $this->em->persist($c);
        return $c;
    }

    /** Flush sans clear : les entités restent managées, les IDs sont disponibles. */
    protected function flush(): void
    {
        $this->em->flush();
    }
}
