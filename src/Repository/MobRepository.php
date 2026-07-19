<?php

namespace App\Repository;

use App\Entity\Mob;
use App\Entity\RaidTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mob::class);
    }

    /** @return Mob[] */
    public function findByRaidTemplateOrdered(RaidTemplate $raidTemplate): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.raidTemplate = :raidTemplate')
            ->setParameter('raidTemplate', $raidTemplate)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
