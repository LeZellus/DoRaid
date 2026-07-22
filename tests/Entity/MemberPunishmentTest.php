<?php

namespace App\Tests\Entity;

use App\Entity\MemberPunishment;
use App\Entity\User;

class MemberPunishmentTest extends EntityTestCase
{
    // ─── isActive() / isPermanent() ─────────────────────────────────────────────

    public function testIsActiveReturnsTrueWhenExpiresAtIsInTheFuture(): void
    {
        $punishment = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('+1 week'));

        $this->assertTrue($punishment->isActive());
        $this->assertFalse($punishment->isPermanent());
    }

    public function testIsActiveReturnsFalseWhenExpiresAtIsInThePast(): void
    {
        $punishment = (new MemberPunishment())->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->assertFalse($punishment->isActive());
        $this->assertFalse($punishment->isPermanent());
    }

    public function testIsActiveReturnsTrueWhenExpiresAtIsNullPermanent(): void
    {
        $punishment = (new MemberPunishment())->setExpiresAt(null);

        $this->assertTrue($punishment->isActive());
        $this->assertTrue($punishment->isPermanent());
    }

    // ─── isWrittenBy() ───────────────────────────────────────────────────────────

    public function testIsWrittenByReturnsTrueForAuthor(): void
    {
        $author     = $this->makeUser(1);
        $punishment = (new MemberPunishment())->setAuthor($author);

        $this->assertTrue($punishment->isWrittenBy($author));
    }

    public function testIsWrittenByReturnsFalseForOtherUser(): void
    {
        $author     = $this->makeUser(1);
        $other      = $this->makeUser(2);
        $punishment = (new MemberPunishment())->setAuthor($author);

        $this->assertFalse($punishment->isWrittenBy($other));
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(int $id): User
    {
        $user = new User();
        $this->setId($user, $id);
        return $user;
    }
}
