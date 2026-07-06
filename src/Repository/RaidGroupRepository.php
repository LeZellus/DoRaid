<?php

namespace App\Repository;

use App\Entity\Raid;
use App\Entity\RaidGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RaidGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RaidGroup::class);
    }

    /**
     * Position du prochain groupe à créer, calculée en base (ne charge pas raid.groups en
     * mémoire — le laisser non initialisé garantit qu'un rendu ultérieur dans la même requête
     * verra bien le groupe fraîchement créé).
     */
    public function nextPosition(Raid $raid): int
    {
        $max = $this->createQueryBuilder('g')
            ->select('MAX(g.position)')
            ->where('g.raid = :raid')
            ->setParameter('raid', $raid)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
