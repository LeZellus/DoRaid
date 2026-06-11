<?php

namespace App\Repository;

use App\Entity\RaidParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RaidParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RaidParticipant::class);
    }

    /** @return RaidParticipant[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('rp')
            ->addSelect('c', 'r', 'rt', 'g')
            ->join('rp.character', 'c')
            ->join('rp.raid', 'r')
            ->join('r.raidTemplate', 'rt')
            ->join('r.guild', 'g')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
