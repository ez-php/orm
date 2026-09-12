<?php

declare(strict_types=1);

namespace Tests\Relations;

use EzPhp\Orm\Relations\EntityHasMany;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Isolated tests for EntityHasMany, beyond what Tests\Entity\RepositoryTest
 * covers indirectly via with()/query(). Focuses on the relation object's own
 * metadata accessors, multi-owner eager loading, and match() grouping.
 *
 * @package Tests\Relations
 */
#[CoversClass(EntityHasMany::class)]
final class EntityHasManyTest extends RelationTestCase
{
    public function test_key_accessors(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        $relation = $this->users->posts($user);

        self::assertSame('id', $relation->getOwnerKey());
        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function test_get_results_returns_empty_list_when_no_related_rows(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertSame([], $this->users->posts($user)->getResults());
    }

    public function test_eager_load_for_batches_across_multiple_owners(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A1')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A2')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (2, 'B1')");

        $alice = $this->users->find(1);
        self::assertNotNull($alice);
        $relation = $this->users->posts($alice);

        $results = $relation->eagerLoadFor([1, 2]);

        self::assertCount(3, $results);
    }

    public function test_match_groups_results_onto_correct_owner_only(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A1')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A2')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (2, 'B1')");

        $users = $this->users->findAll();
        self::assertCount(2, $users);

        $relation = $this->users->posts($users[0]);
        $allPosts = $relation->eagerLoadFor([1, 2]);
        $relation->match($users, $allPosts, 'posts');

        $aliceposts = $users[0]->getAttribute('posts');
        $bobPosts = $users[1]->getAttribute('posts');

        self::assertIsArray($aliceposts);
        self::assertCount(2, $aliceposts);
        self::assertIsArray($bobPosts);
        self::assertCount(1, $bobPosts);
    }

    public function test_count_for_returns_map_keyed_by_owner_id(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A1')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A2')");

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $counts = $this->users->posts($user)->countFor([1, 2]);

        self::assertSame(2, $counts[1]);
        self::assertArrayNotHasKey(2, $counts);
    }

    public function test_count_for_returns_empty_array_for_empty_owner_ids(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertSame([], $this->users->posts($user)->countFor([]));
    }
}
