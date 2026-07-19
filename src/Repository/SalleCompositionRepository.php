<?php

namespace App\Repository;

use App\Entity\RaidTemplate;
use App\Entity\SalleComposition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SalleCompositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalleComposition::class);
    }

    /** @return SalleComposition[] */
    public function findByRaidTemplate(RaidTemplate $raidTemplate): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.salle', 's')
            ->where('s.raidTemplate = :raidTemplate')
            ->setParameter('raidTemplate', $raidTemplate)
            ->orderBy('s.orderNumber', 'ASC')
            ->addOrderBy('c.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
