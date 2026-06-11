<?php

namespace App\Repository;

use App\Entity\GuildMembership;
use App\Entity\MemberStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GuildMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuildMembership::class);
    }

    /** @return GuildMembership[] Pending requests on guilds where $user is leader */
    public function findPendingForLeader(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('c', 'g')
            ->join('m.character', 'c')
            ->join('m.guild', 'g')
            ->join('g.memberships', 'lm')
            ->join('lm.character', 'lc')
            ->where('m.status = :pending')
            ->andWhere('lm.status = :leader')
            ->andWhere('lc.user = :user')
            ->setParameter('pending', MemberStatus::Pending)
            ->setParameter('leader', MemberStatus::Leader)
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return GuildMembership[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.character', 'c')
            ->join('m.guild', 'g')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
