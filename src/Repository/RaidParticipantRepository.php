<?php

namespace App\Repository;

use App\Entity\Raid;
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

    /** @return RaidParticipant[] Pending participants on open raids created by $user */
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
            ->andWhere('r.status = :open')
            ->setParameter('pending', RaidParticipantStatus::Pending)
            ->setParameter('user', $user)
            ->setParameter('open', \App\Entity\RaidStatus::Open)
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

    /** Retourne le personnage accepté d'un utilisateur sur un raid (null si non-participant ou en attente) */
    public function findAcceptedCharacterForUserAndRaid(User $user, Raid $raid): ?RaidParticipant
    {
        return $this->createQueryBuilder('rp')
            ->addSelect('c')
            ->join('rp.character', 'c')
            ->where('c.user = :user')
            ->andWhere('rp.raid = :raid')
            ->andWhere('rp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('raid', $raid)
            ->setParameter('status', RaidParticipantStatus::Accepted)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return RaidParticipant[] Participations sur des raids ouverts pour un utilisateur */
    public function findOpenParticipationsForUser(User $user): array
    {
        return $this->createQueryBuilder('rp')
            ->addSelect('c', 'r', 'rt', 'g', 's')
            ->join('rp.character', 'c')
            ->join('rp.raid', 'r')
            ->join('r.raidTemplate', 'rt')
            ->join('r.guild', 'g')
            ->join('g.server', 's')
            ->where('c.user = :user')
            ->andWhere('r.status = :open')
            ->setParameter('user', $user)
            ->setParameter('open', \App\Entity\RaidStatus::Open)
            ->orderBy('r.scheduledAt', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $characterIds
     * @return array<int, int> Map character ID → nombre de raids terminés en tant que participant accepté
     */
    public function countCompletedByCharacters(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('rp')
            ->select('IDENTITY(rp.character) as charId, COUNT(rp.id) as total')
            ->join('rp.raid', 'r')
            ->where('rp.status = :accepted')
            ->andWhere('r.status = :closed')
            ->andWhere('rp.character IN (:ids)')
            ->setParameter('accepted', RaidParticipantStatus::Accepted)
            ->setParameter('closed', \App\Entity\RaidStatus::Closed)
            ->setParameter('ids', $characterIds)
            ->groupBy('rp.character')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['charId']] = (int) $row['total'];
        }
        return $result;
    }

    /** Vérifie si un utilisateur a candidaté (peu importe le statut) sur un raid */
    public function hasApplied(User $user, Raid $raid): bool
    {
        return (bool) $this->createQueryBuilder('rp')
            ->select('1')
            ->join('rp.character', 'c')
            ->where('c.user = :user')
            ->andWhere('rp.raid = :raid')
            ->setParameter('user', $user)
            ->setParameter('raid', $raid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
