<?php

declare(strict_types=1);

namespace Tests\Relations;

use EzPhp\Orm\Entity;
use EzPhp\Orm\Relations\RelationBatcher;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @package Tests\Relations
 */
#[CoversClass(RelationBatcher::class)]
final class RelationBatcherTest extends RelationTestCase
{
    private function seed(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO rel_users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A1')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (2, 'B1')");
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (1, 'A2')");
        $this->exec("INSERT INTO rel_profiles (user_id, bio) VALUES (1, 'alice bio')");
    }

    public function test_belongs_to_across_entities_uses_one_query(): void
    {
        $this->seed();
        $posts = $this->posts->findAll();
        $batcher = new RelationBatcher();

        $before = $this->db->queryCount;
        $deferred = array_map(fn ($p) => $batcher->belongsTo($this->posts->user($p)), $posts);
        $names = array_map(static function ($d): mixed {
            $u = $d->get();

            return $u instanceof Entity ? $u->getAttribute('name') : null;
        }, $deferred);

        self::assertSame(['Alice', 'Bob', 'Alice'], $names);
        self::assertSame(1, $this->db->queryCount - $before);
    }

    public function test_belongs_to_with_null_fk_resolves_to_null_without_a_query(): void
    {
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (NULL, 'Orphan')");
        $post = $this->posts->find(1);
        self::assertNotNull($post);
        $batcher = new RelationBatcher();

        $before = $this->db->queryCount;
        self::assertNull($batcher->belongsTo($this->posts->user($post))->get());
        self::assertSame(0, $this->db->queryCount - $before);
    }

    public function test_belongs_to_with_dangling_fk_resolves_to_null(): void
    {
        $this->exec("INSERT INTO rel_posts (user_id, title) VALUES (99, 'Dangling')");
        $post = $this->posts->find(1);
        self::assertNotNull($post);

        self::assertNull((new RelationBatcher())->belongsTo($this->posts->user($post))->get());
    }

    public function test_has_many_across_owners_uses_one_query_and_groups_correctly(): void
    {
        $this->seed();
        $users = $this->users->findAll();
        $batcher = new RelationBatcher();

        $before = $this->db->queryCount;
        $deferred = array_map(fn ($u) => $batcher->hasMany($this->users->posts($u)), $users);
        $titles = array_map(static function ($d): array {
            /** @var list<\EzPhp\Orm\Entity> $posts */
            $posts = $d->get();

            return array_map(static fn ($p) => $p->getAttribute('title'), $posts);
        }, $deferred);

        self::assertSame([['A1', 'A2'], ['B1']], $titles);
        self::assertSame(1, $this->db->queryCount - $before);
    }

    public function test_has_many_without_children_resolves_to_empty_list(): void
    {
        $this->exec("INSERT INTO rel_users (name) VALUES ('Lonely')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        self::assertSame([], (new RelationBatcher())->hasMany($this->users->posts($user))->get());
    }

    public function test_has_one_across_owners_uses_one_query(): void
    {
        $this->seed();
        $users = $this->users->findAll();
        $batcher = new RelationBatcher();

        $before = $this->db->queryCount;
        $deferred = array_map(fn ($u) => $batcher->hasOne($this->users->profile($u)), $users);
        $bios = array_map(static function ($d): mixed {
            $p = $d->get();

            return $p instanceof Entity ? $p->getAttribute('bio') : null;
        }, $deferred);

        self::assertSame(['alice bio', null], $bios);
        self::assertSame(1, $this->db->queryCount - $before);
    }

    public function test_repeated_loads_of_the_same_key_are_memoized(): void
    {
        $this->seed();
        $posts = $this->posts->findAll();
        $batcher = new RelationBatcher();

        $batcher->belongsTo($this->posts->user($posts[0]))->get();
        $before = $this->db->queryCount;
        $again = $batcher->belongsTo($this->posts->user($posts[2]))->get(); // same user id 1

        self::assertNotNull($again);
        self::assertSame(0, $this->db->queryCount - $before);
    }
}
