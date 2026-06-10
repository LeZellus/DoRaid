<?php

namespace App\Repository;

use App\Entity\EnigmeTemplate;
use App\Entity\RaidTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EnigmeTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnigmeTemplate::class);
    }

    /** @return EnigmeTemplate[] ordered by orderNumber */
    public function findByTemplate(RaidTemplate $template): array
    {
        return $this->createQueryBuilder('et')
            ->where('et.raidTemplate = :template')
            ->setParameter('template', $template)
            ->orderBy('et.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
