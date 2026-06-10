<?php

namespace App\Repository;

use App\Entity\Character;
use App\Entity\Guild;
use App\Entity\MemberStatus;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\Server;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CharacterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Character::class);
    }

    /** Characters of a user on a given server that have no guild membership yet */
    public function findEligibleForGuild(User $user, Server $server): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.server = :server')
            ->andWhere('NOT EXISTS (SELECT m FROM App\Entity\GuildMembership m WHERE m.character = c)')
            ->setParameter('user', $user)
            ->setParameter('server', $server)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Confirmed guild members of $user in $guild (non-Pending memberships) */
    public function findConfirmedInGuild(User $user, Guild $guild): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.membership', 'm')
            ->where('c.user = :user')
            ->andWhere('m.guild = :guild')
            ->andWhere('m.status != :pending')
            ->setParameter('user', $user)
            ->setParameter('guild', $guild)
            ->setParameter('pending', MemberStatus::Pending)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Confirmed guild members of $user in the raid's guild that haven't joined the raid yet */
    public function findEligibleForRaid(User $user, Raid $raid): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.membership', 'm')
            ->leftJoin(
                RaidParticipant::class, 'rp',
                'WITH', 'rp.character = c AND rp.raid = :raid'
            )
            ->where('c.user = :user')
            ->andWhere('m.guild = :guild')
            ->andWhere('m.status != :pending')
            ->andWhere('rp.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('raid', $raid)
            ->setParameter('guild', $raid->getGuild())
            ->setParameter('pending', MemberStatus::Pending)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Character[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.server', 's')
            ->join('c.gameClass', 'g')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
