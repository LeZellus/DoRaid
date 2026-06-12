<?php

namespace App\Service;

use App\Entity\Enigme;
use App\Entity\Raid;
use App\Repository\EnigmeTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class EnigmeSyncService
{
    public function __construct(private readonly EnigmeTemplateRepository $enigmeTemplateRepo) {}

    /**
     * Persists Enigmes for any EnigmeTemplates added to the RaidTemplate after raid creation.
     * Returns true if anything was persisted (caller must flush).
     */
    public function syncFromTemplate(Raid $raid, EntityManagerInterface $em): bool
    {
        $existingIds = array_map(
            fn($e) => $e->getSourceTemplate()->getId(),
            $raid->getEnigmes()->toArray()
        );

        $added = false;
        foreach ($this->enigmeTemplateRepo->findByTemplate($raid->getRaidTemplate()) as $et) {
            if (!in_array($et->getId(), $existingIds, true)) {
                $em->persist((new Enigme())
                    ->setRaid($raid)
                    ->setOrderNumber($et->getOrderNumber())
                    ->setSourceTemplate($et)
                );
                $added = true;
            }
        }

        return $added;
    }
}
