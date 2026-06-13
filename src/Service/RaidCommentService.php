<?php

namespace App\Service;

use App\Entity\Raid;
use App\Entity\RaidComment;
use App\Entity\User;
use App\Exception\BusinessRuleException;
use Doctrine\ORM\EntityManagerInterface;

class RaidCommentService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function addComment(Raid $raid, User $author, string $content): void
    {
        $this->em->persist(
            (new RaidComment())->setRaid($raid)->setAuthor($author)->setContent($content)
        );
        $this->em->flush();
    }

    public function addReply(RaidComment $parent, User $author, string $content): void
    {
        if ($parent->getParent() !== null) {
            throw new BusinessRuleException('Impossible de répondre à une réponse.');
        }

        $this->em->persist(
            (new RaidComment())->setRaid($parent->getRaid())->setAuthor($author)->setContent($content)->setParent($parent)
        );
        $this->em->flush();
    }

    public function deleteComment(RaidComment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }
}
