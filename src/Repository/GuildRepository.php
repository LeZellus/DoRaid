<?php

namespace App\Repository;

use App\Entity\Guild;
use App\Entity\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GuildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Guild::class);
    }

    /** @return Guild[] ordered by server then name */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.server', 's')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByServer(Server $server): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.server = :server')
            ->setParameter('server', $server)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
