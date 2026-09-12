<?php

declare(strict_types=1);

namespace Tests\Relations;

use EzPhp\Orm\Relations\EntityBelongsToMany;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Isolated tests for EntityBelongsToMany — the most complex relation, joining
 * through a pivot table via raw SQL. Focuses on the previously-untested edge
 * cases: a pivot row pointing at a related row that no longer exists (an
 * orphaned/"missing" pivot row), and a duplicated pivot row for the same
 * (owner, related) pair.
 *
 * @package Tests\Relations
 */
#[CoversClass(EntityBelongsToMany::class)]
final class EntityBelongsToManyTest extends RelationTestCase
{
    public function test_key_accessors(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        $relation = $this->users->tags($user);

        self::assertSame('id', $relation->getOwnerKey());
        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function test_get_results_returns_empty_list_when_no_pivot_rows(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertSame([], $this->users->tags($user)->getResults());
    }

    /**
     * A pivot row referencing a tag_id that has since been deleted from
     * rel_tags must not appear in the results (the INNER JOIN naturally
     * excludes it) and must not cause an error.
     */
    public function test_get_results_silently_excludes_orphaned_pivot_row(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('PHP')");
        // Pivot row pointing at tag_id 99, which does not exist.
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 99)');
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $tags = $this->users->tags($user)->getResults();

        self::assertCount(1, $tags);
        self::assertSame('PHP', $tags[0]->getAttribute('name'));
    }

    /**
     * A duplicated pivot row (same user_id + tag_id inserted twice) must
     * surface the related entity twice — the relation does not deduplicate,
     * since the JOIN legitimately produces one row per pivot row.
     */
    public function test_get_results_reflects_duplicated_pivot_row_without_deduplication(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('PHP')");
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $tags = $this->users->tags($user)->getResults();

        self::assertCount(2, $tags);
        self::assertSame('PHP', $tags[0]->getAttribute('name'));
        self::assertSame('PHP', $tags[1]->getAttribute('name'));
    }

    public function test_eager_load_for_batches_across_multiple_owners(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('PHP')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('ORM')");
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (2, 2)');

        $alice = $this->users->find(1);
        self::assertNotNull($alice);
        $relation = $this->users->tags($alice);

        $results = $relation->eagerLoadFor([1, 2]);

        self::assertCount(2, $results);
    }

    public function test_eager_load_for_returns_empty_list_for_empty_ids(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertSame([], $this->users->tags($user)->eagerLoadFor([]));
    }

    /**
     * match() groups eager-loaded rows back onto the correct owner via the
     * synthetic __pivot_fk column — this must remain correct even with a
     * duplicated pivot row and an orphaned one mixed in.
     */
    public function test_match_groups_by_pivot_fk_with_duplicates_and_orphans_present(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('PHP')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('ORM')");
        // Alice: PHP twice (duplicate) + an orphaned pivot row (tag_id 99).
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 99)');
        // Bob: ORM once.
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (2, 2)');

        $users = $this->users->findAll();
        self::assertCount(2, $users);

        $relation = $this->users->tags($users[0]);
        $allTags = $relation->eagerLoadFor([1, 2]);
        $relation->match($users, $allTags, 'tags');

        $aliceTags = $users[0]->getAttribute('tags');
        $bobTags = $users[1]->getAttribute('tags');

        self::assertIsArray($aliceTags);
        self::assertCount(2, $aliceTags, 'the duplicate PHP pivot row yields two entries; the orphan yields none');
        self::assertIsArray($bobTags);
        self::assertCount(1, $bobTags);
        self::assertInstanceOf(RelTagEntity::class, $bobTags[0]);
        self::assertSame('ORM', $bobTags[0]->getAttribute('name'));
    }

    public function test_count_for_counts_pivot_rows_not_distinct_related_entities(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_tags (name) VALUES ('PHP')");
        // Same tag linked twice — countFor() counts pivot rows, so this must be 2.
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO rel_user_tags (user_id, tag_id) VALUES (1, 1)');

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $counts = $this->users->tags($user)->countFor([1]);

        self::assertSame(2, $counts[1]);
    }

    public function test_count_for_returns_empty_array_for_empty_owner_ids(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertSame([], $this->users->tags($user)->countFor([]));
    }
}
