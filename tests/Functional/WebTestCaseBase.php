<?php

namespace App\Tests\Functional;

use App\Entity\Character;
use App\Entity\Enigme;
use App\Entity\EnigmeComment;
use App\Entity\EnigmeImage;
use App\Entity\EnigmeTemplate;
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
            'raid_comment', 'raid_participant', 'raid_group', 'raid',
            'guild_membership', 'game_character', 'guild',
            'user', 'server', 'game_class', 'raid_template', 'enigme_template',
            'salle_composition_mob', 'salle_composition', 'salle', 'mob_drop_rate', 'mob', 'gem',
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

    /** Suite de lettres minuscules aléatoires, conforme au format de pseudo (pas de chiffre). */
    protected function randomLetters(int $length = 8): string
    {
        $letters = 'abcdefghijklmnopqrstuvwxyz';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $letters[random_int(0, 25)];
        }
        return $out;
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
            ->setName('Perso' . $this->randomLetters())
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

    protected function makeRaidGroup(Raid $raid, ?string $label = null, int $position = 1): \App\Entity\RaidGroup
    {
        $g = (new \App\Entity\RaidGroup())->setRaid($raid)->setLabel($label)->setPosition($position);
        $this->em->persist($g);
        return $g;
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

    protected function makeEnigmeTemplate(RaidTemplate $raidTemplate, int $orderNumber = 1): EnigmeTemplate
    {
        $t = (new EnigmeTemplate())
            ->setRaidTemplate($raidTemplate)
            ->setTitle('Enigme-' . uniqid('', true))
            ->setOrderNumber($orderNumber);
        $this->em->persist($t);
        return $t;
    }

    protected function makeEnigme(Raid $raid, int $orderNumber = 1): Enigme
    {
        $enigme = (new Enigme())
            ->setRaid($raid)
            ->setSourceTemplate($this->makeEnigmeTemplate($raid->getRaidTemplate(), $orderNumber))
            ->setOrderNumber($orderNumber);
        $this->em->persist($enigme);
        return $enigme;
    }

    protected function makeEnigmeComment(Enigme $enigme, Character $author, string $content = 'Commentaire'): EnigmeComment
    {
        $c = (new EnigmeComment())->setEnigme($enigme)->setAuthor($author)->setContent($content);
        $this->em->persist($c);
        return $c;
    }

    protected function makeEnigmeImage(Enigme $enigme, Character $addedBy, string $filePath = 'preuve.png'): EnigmeImage
    {
        $img = (new EnigmeImage())->setEnigme($enigme)->setAddedBy($addedBy)->setFilePath($filePath);
        $this->em->persist($img);
        return $img;
    }

    protected function makeGem(string $name = 'Quartz', int $value = 2): \App\Entity\Gem
    {
        $gem = (new \App\Entity\Gem())->setName($name . '-' . uniqid('', true))->setValue($value);
        $this->em->persist($gem);
        return $gem;
    }

    protected function makeMob(RaidTemplate $raidTemplate, string $name = 'Mob'): \App\Entity\Mob
    {
        $mob = (new \App\Entity\Mob())->setRaidTemplate($raidTemplate)->setName($name . '-' . uniqid('', true));
        $this->em->persist($mob);
        return $mob;
    }

    protected function makeMobDropRate(\App\Entity\Mob $mob, \App\Entity\Gem $gem, float $probability): \App\Entity\MobDropRate
    {
        $rate = (new \App\Entity\MobDropRate())->setMob($mob)->setGem($gem)->setProbability($probability);
        $this->em->persist($rate);
        return $rate;
    }

    protected function makeSalle(RaidTemplate $raidTemplate, int $levelMin = 1, int $levelMax = 10, int $orderNumber = 1): \App\Entity\Salle
    {
        $salle = (new \App\Entity\Salle())
            ->setRaidTemplate($raidTemplate)
            ->setName('Salle-' . uniqid('', true))
            ->setLevelMin($levelMin)
            ->setLevelMax($levelMax)
            ->setOrderNumber($orderNumber);
        $this->em->persist($salle);
        return $salle;
    }

    protected function makeSalleComposition(\App\Entity\Salle $salle, int $orderNumber = 1): \App\Entity\SalleComposition
    {
        $composition = (new \App\Entity\SalleComposition())->setSalle($salle)->setOrderNumber($orderNumber);
        $this->em->persist($composition);
        return $composition;
    }

    protected function makeSalleCompositionMob(\App\Entity\SalleComposition $composition, \App\Entity\Mob $mob, int $quantity = 1): \App\Entity\SalleCompositionMob
    {
        $scm = (new \App\Entity\SalleCompositionMob())->setComposition($composition)->setMob($mob)->setQuantity($quantity);
        $this->em->persist($scm);
        return $scm;
    }

    /** Flush sans clear : les entités restent managées, les IDs sont disponibles. */
    protected function flush(): void
    {
        $this->em->flush();
    }
}
