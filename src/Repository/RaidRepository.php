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

    /** @return Raid[] Raids visibles par un utilisateur (publics + guildes de l'utilisateur), filtrés par serveur */
    public function findVisibleForUser(array $userGuildIds = [], ?string $serverName = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.guild', 'g')
            ->join('g.server', 's')
            ->orderBy('r.createdAt', 'DESC');

        if (!empty($userGuildIds)) {
            $qb->where(
                $qb->expr()->orX(
                    'r.isPublic = true',
                    $qb->expr()->in('g', ':guildIds')
                )
            )->setParameter('guildIds', $userGuildIds);
        } else {
            $qb->where('r.isPublic = true');
        }

        if ($serverName) {
            $qb->andWhere('s.name = :server')
               ->setParameter('server', $serverName);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Raid[] */
    public function findPublicOpen(array $excludeGuildIds = []): array
    {
        $qb = $this->createQueryBuilder('r')
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
            ->where('r.guild = :guild')
            ->setParameter('guild', $guild)
            ->orderBy('r.scheduledAt', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
