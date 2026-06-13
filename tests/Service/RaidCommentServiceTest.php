<?php

namespace App\Tests\Service;

use App\Entity\RaidComment;
use App\Exception\BusinessRuleException;
use App\Service\RaidCommentService;
use App\Tests\Functional\WebTestCaseBase;

class RaidCommentServiceTest extends WebTestCaseBase
{
    private function service(): RaidCommentService
    {
        return static::getContainer()->get(RaidCommentService::class);
    }

    // ─── addComment ──────────────────────────────────────────────────────────

    public function testAddCommentPersistsComment(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar);
        $this->flush();
        $raidId = $raid->getId();

        $this->service()->addComment($raid, $owner, 'Super raid !');

        $this->em->clear();
        $reloadedRaid = $this->em->find(\App\Entity\Raid::class, $raidId);
        $this->assertCount(1, $reloadedRaid->getComments());
        $this->assertSame('Super raid !', $reloadedRaid->getComments()->first()->getContent());
    }

    // ─── addReply ────────────────────────────────────────────────────────────

    public function testAddReplyPersistsReply(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar);
        $parent    = $this->makeRaidComment($raid, $owner, 'Parent');
        $this->flush();
        $parentId = $parent->getId();

        $replier = $this->makeUser('replier@test.com');
        $this->flush();

        $this->service()->addReply($parent, $replier, 'Ma réponse');

        $this->em->clear();
        $reloadedParent = $this->em->find(RaidComment::class, $parentId);
        $this->assertCount(1, $reloadedParent->getChildren());
        $this->assertSame('Ma réponse', $reloadedParent->getChildren()->first()->getContent());
    }

    public function testAddReplyToChildThrowsBusinessRuleException(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar);
        $root      = $this->makeRaidComment($raid, $owner, 'Parent');
        $child     = $this->makeRaidComment($raid, $owner, 'Reply', $root);
        $this->flush();

        $this->expectException(BusinessRuleException::class);
        $this->service()->addReply($child, $owner, 'Tentative de réponse imbriquée');
    }

    // ─── deleteComment ───────────────────────────────────────────────────────

    public function testDeleteCommentRemovesIt(): void
    {
        $server    = $this->makeServer();
        $owner     = $this->makeUser('owner@test.com');
        $ownerChar = $this->makeCharacter($owner, $server);
        $guild     = $this->makeGuild($owner, $server);
        $raid      = $this->makeRaid($guild, $ownerChar);
        $comment   = $this->makeRaidComment($raid, $owner, 'À supprimer');
        $this->flush();
        $commentId = $comment->getId();

        $this->service()->deleteComment($comment);

        $this->assertNull($this->em->find(RaidComment::class, $commentId));
    }
}
