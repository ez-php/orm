<?php

declare(strict_types=1);

namespace Tests\Relations;

use EzPhp\Orm\Relations\EntityHasOne;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Isolated tests for EntityHasOne, beyond what Tests\Entity\RepositoryTest
 * covers indirectly via with()/query().
 *
 * @package Tests\Relations
 */
#[CoversClass(EntityHasOne::class)]
final class EntityHasOneTest extends RelationTestCase
{
    public function test_key_accessors(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        $relation = $this->users->profile($user);

        self::assertSame('id', $relation->getOwnerKey());
        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function test_get_result_returns_null_when_no_related_row(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertNull($this->users->profile($user)->getResult());
    }

    public function test_get_result_returns_matching_entity(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_profiles (user_id, bio) VALUES (1, 'Hello')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        $profile = $this->users->profile($user)->getResult();

        self::assertNotNull($profile);
        self::assertSame('Hello', $profile->getAttribute('bio'));
    }

    public function test_match_assigns_first_match_per_owner_and_null_when_absent(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_profiles (user_id, bio) VALUES (1, 'Alice bio')");

        $users = $this->users->findAll();
        self::assertCount(2, $users);

        $relation = $this->users->profile($users[0]);
        $profiles = $relation->eagerLoadFor([1, 2]);
        $relation->match($users, $profiles, 'profile');

        self::assertNotNull($users[0]->getAttribute('profile'));
        self::assertNull($users[1]->getAttribute('profile'));
    }

    public function test_count_for_returns_zero_or_one_per_owner(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_profiles (user_id, bio) VALUES (1, 'Hello')");

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $counts = $this->users->profile($user)->countFor([1, 2]);

        self::assertSame(1, $counts[1]);
        self::assertArrayNotHasKey(2, $counts);
    }
}
