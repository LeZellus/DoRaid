<?php

namespace App\Repository;

use App\Entity\RaidTemplate;
use App\Entity\Salle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SalleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Salle::class);
    }

    /** @return Salle[] */
    public function findByRaidTemplateOrdered(RaidTemplate $raidTemplate): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.raidTemplate = :raidTemplate')
            ->setParameter('raidTemplate', $raidTemplate)
            ->orderBy('s.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
