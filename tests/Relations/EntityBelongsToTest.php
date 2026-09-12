<?php

declare(strict_types=1);

namespace Tests\Relations;

use EzPhp\Orm\Relations\EntityBelongsTo;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Isolated tests for EntityBelongsTo, beyond what Tests\Entity\RepositoryTest
 * covers indirectly via with()/query().
 *
 * @package Tests\Relations
 */
#[CoversClass(EntityBelongsTo::class)]
final class EntityBelongsToTest extends RelationTestCase
{
    public function test_key_accessors(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'Post A')");
        $post = $this->posts->find(1);
        self::assertNotNull($post);

        $relation = $this->posts->user($post);

        // For BelongsTo the FK lives on the OWNING side, so getOwnerKey()
        // returns the foreign key column, not a primary key.
        self::assertSame('user_id', $relation->getOwnerKey());
        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function test_get_result_returns_null_when_fk_value_is_not_scalar_id(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        // A post whose user_id column is NULL: getAttribute() returns null,
        // which is neither int nor string, so getResult() must return null
        // rather than calling find(null).
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (NULL, 'Orphan')");
        $post = $this->posts->find(1);
        self::assertNotNull($post);

        self::assertNull($this->posts->user($post)->getResult());
    }

    public function test_get_result_returns_owning_entity(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'Post A')");
        $post = $this->posts->find(1);
        self::assertNotNull($post);

        $user = $this->posts->user($post)->getResult();

        self::assertNotNull($user);
        self::assertSame('Alice', $user->getAttribute('name'));
    }

    public function test_match_assigns_correct_owner_to_each_post(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'Post A')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (2, 'Post B')");

        $posts = $this->posts->findAll();
        self::assertCount(2, $posts);

        $relation = $this->posts->user($posts[0]);
        $users = $relation->eagerLoadFor([1, 2]);
        $relation->match($posts, $users, 'user');

        $userForPostA = $posts[0]->getAttribute('user');
        $userForPostB = $posts[1]->getAttribute('user');

        self::assertInstanceOf(RelUserEntity::class, $userForPostA);
        self::assertSame('Alice', $userForPostA->getAttribute('name'));
        self::assertInstanceOf(RelUserEntity::class, $userForPostB);
        self::assertSame('Bob', $userForPostB->getAttribute('name'));
    }

    public function test_match_assigns_null_when_owner_missing(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (99, 'Dangling')");

        $post = $this->posts->find(1);
        self::assertNotNull($post);

        $relation = $this->posts->user($post);
        $users = $relation->eagerLoadFor([99]);
        $relation->match([$post], $users, 'user');

        self::assertNull($post->getAttribute('user'));
    }

    public function test_count_for_counts_matching_related_rows_by_local_key(): void
    {
        // For BelongsTo, countFor() counts rows on the RELATED side (users)
        // whose primary key matches the given values — i.e. existence, since
        // 'id' is a primary key (0 or 1 per value), not "posts per user".
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A')");

        $post = $this->posts->find(1);
        self::assertNotNull($post);

        $counts = $this->posts->user($post)->countFor([1, 2]);

        self::assertSame(1, $counts[1]);
        self::assertArrayNotHasKey(2, $counts);
    }
}
