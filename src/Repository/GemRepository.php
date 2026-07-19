<?php

namespace App\Repository;

use App\Entity\Gem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gem::class);
    }

    /** @return Gem[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.value', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
