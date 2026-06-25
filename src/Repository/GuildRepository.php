<?php

namespace App\Repository;

use App\Entity\Guild;
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

    /** Charge la guilde avec ses membres (personnage, classe, utilisateur) pour éviter le N+1 d'affichage. */
    public function findBySlugWithMembers(string $slug): ?Guild
    {
        return $this->createQueryBuilder('g')
            ->addSelect('s', 'm', 'mc', 'mgc', 'mu')
            ->join('g.server', 's')
            ->leftJoin('g.memberships', 'm')
            ->leftJoin('m.character', 'mc')
            ->leftJoin('mc.gameClass', 'mgc')
            ->leftJoin('mc.user', 'mu')
            ->where('g.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
