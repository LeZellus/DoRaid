<?php

namespace App\Repository;

use App\Entity\Character;
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
            ->leftJoin('c.membership', 'm')
            ->where('c.user = :user')
            ->andWhere('c.server = :server')
            ->andWhere('m.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('server', $server)
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
