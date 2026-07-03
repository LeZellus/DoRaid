<?php

namespace App\Repository;

use App\Entity\Feedback;
use App\Entity\FeedbackStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :status')
            ->setParameter('status', FeedbackStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
