<?php

namespace App\Repository;

use App\Entity\Raid;
use App\Entity\RaidComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RaidCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RaidComment::class);
    }

    /** Root-level comments for a raid, with replies and authors eagerly loaded */
    public function findRootByRaid(Raid $raid): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'r')->addSelect('r')
            ->leftJoin('c.author', 'a')->addSelect('a')
            ->leftJoin('r.author', 'ra')->addSelect('ra')
            ->where('c.raid = :raid')
            ->andWhere('c.parent IS NULL')
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->setParameter('raid', $raid)
            ->getQuery()
            ->getResult();
    }
}
