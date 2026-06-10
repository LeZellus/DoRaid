<?php

namespace App\Repository;

use App\Entity\GuildMembership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GuildMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuildMembership::class);
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
