<?php

namespace App\Repository;

use App\Entity\Guild;
use App\Entity\Raid;
use App\Entity\RaidStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RaidRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Raid::class);
    }

    /** @return Raid[] */
    public function findPublicOpen(array $excludeGuildIds = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('g', 's', 'rt')
            ->join('r.guild', 'g')
            ->join('g.server', 's')
            ->join('r.raidTemplate', 'rt')
            ->where('r.isPublic = true')
            ->andWhere('r.status = :status')
            ->setParameter('status', RaidStatus::Open)
            ->orderBy('r.createdAt', 'DESC');

        if (!empty($excludeGuildIds)) {
            $qb->andWhere('r.guild NOT IN (:guilds)')
               ->setParameter('guilds', $excludeGuildIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Raid[] */
    public function findByGuild(Guild $guild): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('rt', 'creator', 'p')
            ->join('r.raidTemplate', 'rt')
            ->join('r.creator', 'creator')
            ->leftJoin('r.participants', 'p')
            ->where('r.guild = :guild')
            ->setParameter('guild', $guild)
            ->orderBy('r.scheduledAt', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns open raids split into three pre-sorted groups for the index page.
     * @return array{upcoming: Raid[], started: Raid[], ongoing: Raid[]}
     */
    public function findGroupedOpen(array $userGuildIds = [], ?string $serverName = null): array
    {
        $now   = new \DateTimeImmutable();
        $raids = $this->buildVisibleQb($userGuildIds, $serverName)
            ->andWhere('r.status = :open')
            ->setParameter('open', RaidStatus::Open)
            ->getQuery()->getResult();

        $upcoming = [];
        $started  = [];
        $ongoing  = [];

        foreach ($raids as $r) {
            $scheduledAt = $r->getScheduledAt();
            if ($scheduledAt === null) {
                $ongoing[] = $r;
            } elseif ($scheduledAt > $now) {
                $upcoming[] = $r;
            } else {
                $started[] = $r;
            }
        }

        usort($upcoming, fn($a, $b) => $a->getScheduledAt() <=> $b->getScheduledAt());
        usort($started,  fn($a, $b) => $b->getScheduledAt() <=> $a->getScheduledAt());
        usort($ongoing,  fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return ['upcoming' => $upcoming, 'started' => $started, 'ongoing' => $ongoing];
    }

    /** @return Raid[] All public raids with minimal joins, for sitemap generation */
    public function findPublicForSitemap(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isPublic = true')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Raid[] Open raids with a scheduled date and a template duration (candidates for auto-close) */
    public function findAllOpen(): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.raidTemplate', 'rt')
            ->where('r.status = :open')
            ->andWhere('r.scheduledAt IS NOT NULL')
            ->andWhere('rt.duration IS NOT NULL')
            ->setParameter('open', RaidStatus::Open)
            ->getQuery()
            ->getResult();
    }

    /** Charge le raid avec ses participants (personnage, classe, utilisateur, adhésion) pour éviter le N+1 d'affichage. */
    public function findWithParticipants(int $id): ?Raid
    {
        return $this->createQueryBuilder('r')
            ->addSelect('rt', 'creator', 'g', 's', 'p', 'pc', 'pcgc', 'pcu', 'pcm')
            ->join('r.raidTemplate', 'rt')
            ->join('r.creator', 'creator')
            ->join('r.guild', 'g')
            ->join('g.server', 's')
            ->leftJoin('r.participants', 'p')
            ->leftJoin('p.character', 'pc')
            ->leftJoin('pc.gameClass', 'pcgc')
            ->leftJoin('pc.user', 'pcu')
            ->leftJoin('pc.membership', 'pcm')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Raid[] Autres raids ouverts de la même guilde — utilisé pour déplacer un participant. */
    public function findOpenByGuild(Guild $guild, Raid $exclude): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('rt', 'creator')
            ->join('r.raidTemplate', 'rt')
            ->join('r.creator', 'creator')
            ->where('r.guild = :guild')
            ->andWhere('r.status = :open')
            ->andWhere('r != :exclude')
            ->setParameter('guild', $guild)
            ->setParameter('open', RaidStatus::Open)
            ->setParameter('exclude', $exclude)
            ->orderBy('r.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function buildVisibleQb(array $userGuildIds, ?string $serverName): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('g', 's', 'rt', 'creator', 'gc', 'p')
            ->join('r.guild', 'g')
            ->join('g.server', 's')
            ->join('r.raidTemplate', 'rt')
            ->join('r.creator', 'creator')
            ->join('creator.gameClass', 'gc')
            ->leftJoin('r.participants', 'p');

        if (!empty($userGuildIds)) {
            $qb->where($qb->expr()->orX('r.isPublic = true', $qb->expr()->in('g', ':guildIds')))
               ->setParameter('guildIds', $userGuildIds);
        } else {
            $qb->where('r.isPublic = true');
        }

        if ($serverName) {
            $qb->andWhere('s.name = :server')->setParameter('server', $serverName);
        }

        return $qb;
    }
}
