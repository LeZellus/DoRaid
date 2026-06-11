<?php

namespace App\Repository;

use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RaidParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RaidParticipant::class);
    }

    /** @return RaidParticipant[] Pending participants on raids created by $user */
    public function findPendingForCreator(User $user): array
    {
        return $this->createQueryBuilder('rp')
            ->addSelect('c', 'r', 'rt', 'g')
            ->join('rp.character', 'c')
            ->join('rp.raid', 'r')
            ->join('r.raidTemplate', 'rt')
            ->join('r.guild', 'g')
            ->join('r.creator', 'creator')
            ->join('creator.user', 'cu')
            ->where('rp.status = :pending')
            ->andWhere('cu = :user')
            ->setParameter('pending', RaidParticipantStatus::Pending)
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
