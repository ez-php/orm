<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Hydrator;
use EzPhp\Orm\Paginator;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class PaginationTest
 *
 * Integration tests for QueryBuilder::paginate() / chunk() and the mirrored
 * methods on EntityQueryBuilder. Uses an in-memory SQLite database seeded with
 * 10 rows so pagination arithmetic is easy to verify.
 *
 * @package Tests
 */
#[CoversClass(QueryBuilder::class)]
#[CoversClass(EntityQueryBuilder::class)]
#[UsesClass(Paginator::class)]
#[UsesClass(Entity::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(Hydrator::class)]
final class PaginationTest extends RepositoryTestCase
{
    private PaginationItemRepository $items;

    /**
     * @return void
     */
    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        for ($i = 1; $i <= 10; $i++) {
            $this->exec("INSERT INTO items (name) VALUES ('item-$i')");
        }

        $this->items = new PaginationItemRepository($this->db, $this->hydrator);
    }

    // ── QueryBuilder::paginate() ──────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_qb_paginate_returns_paginator(): void
    {
        $paginator = (new QueryBuilder($this->db, 'items'))->paginate(3, 1);
        $this->assertInstanceOf(Paginator::class, $paginator);
    }

    /**
     * @return void
     */
    public function test_qb_paginate_first_page_items(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 1);
        $this->assertCount(3, $p->items());
        $this->assertSame('item-1', $p->items()[0]['name']);
        $this->assertSame('item-3', $p->items()[2]['name']);
    }

    /**
     * @return void
     */
    public function test_qb_paginate_second_page_items(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 2);
        $this->assertCount(3, $p->items());
        $this->assertSame('item-4', $p->items()[0]['name']);
    }

    /**
     * @return void
     */
    public function test_qb_paginate_last_partial_page(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 4); // 10 rows, page 4 → 1 item
        $this->assertCount(1, $p->items());
        $this->assertSame('item-10', $p->items()[0]['name']);
    }

    /**
     * @return void
     */
    public function test_qb_paginate_total(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 1);
        $this->assertSame(10, $p->total());
    }

    /**
     * @return void
     */
    public function test_qb_paginate_last_page(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 1);
        $this->assertSame(4, $p->lastPage()); // ceil(10/3) = 4
    }

    /**
     * @return void
     */
    public function test_qb_paginate_has_more_pages_true(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 1);
        $this->assertTrue($p->hasMorePages());
    }

    /**
     * @return void
     */
    public function test_qb_paginate_has_more_pages_false_on_last_page(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate(3, 4);
        $this->assertFalse($p->hasMorePages());
    }

    /**
     * @return void
     */
    public function test_qb_paginate_with_where_filter(): void
    {
        // Only items 1..5 have id <= 5
        $p = (new QueryBuilder($this->db, 'items'))->where('id', '<=', 5)->paginate(3, 1);
        $this->assertSame(5, $p->total());
        $this->assertSame(2, $p->lastPage()); // ceil(5/3) = 2
        $this->assertCount(3, $p->items());
    }

    /**
     * @return void
     */
    public function test_qb_paginate_default_page_and_per_page(): void
    {
        $p = (new QueryBuilder($this->db, 'items'))->paginate();
        $this->assertSame(1, $p->currentPage());
        $this->assertSame(15, $p->perPage());
        $this->assertCount(10, $p->items()); // all 10 fit in one page of 15
    }

    // ── QueryBuilder::chunk() ─────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_qb_chunk_visits_all_rows(): void
    {
        $collected = [];
        (new QueryBuilder($this->db, 'items'))->chunk(3, function (array $rows) use (&$collected): void {
            foreach ($rows as $row) {
                $collected[] = $row['name'];
            }
        });

        $this->assertCount(10, $collected);
        $this->assertSame('item-1', $collected[0]);
        $this->assertSame('item-10', $collected[9]);
    }

    /**
     * @return void
     */
    public function test_qb_chunk_calls_callback_per_chunk(): void
    {
        $calls = 0;
        (new QueryBuilder($this->db, 'items'))->chunk(3, function (array $rows) use (&$calls): void {
            $calls++;
        });

        $this->assertSame(4, $calls); // 3+3+3+1
    }

    /**
     * @return void
     */
    public function test_qb_chunk_on_empty_table_calls_no_callback(): void
    {
        $this->exec('DELETE FROM items');
        $calls = 0;
        (new QueryBuilder($this->db, 'items'))->chunk(10, function (array $rows) use (&$calls): void {
            $calls++;
        });

        $this->assertSame(0, $calls);
    }

    /**
     * @return void
     */
    public function test_qb_chunk_chunk_size_larger_than_total(): void
    {
        $collected = [];
        (new QueryBuilder($this->db, 'items'))->chunk(100, function (array $rows) use (&$collected): void {
            $collected = $rows;
        });

        $this->assertCount(10, $collected);
    }

    // ── EntityQueryBuilder::paginate() ───────────────────────────────────────

    /**
     * @return void
     */
    public function test_eqb_paginate_returns_paginator_of_entities(): void
    {
        $p = $this->items->query()->paginate(3, 1);
        $this->assertInstanceOf(Paginator::class, $p);
        $this->assertInstanceOf(PaginationItem::class, $p->items()[0]);
    }

    /**
     * @return void
     */
    public function test_eqb_paginate_total_and_last_page(): void
    {
        $p = $this->items->query()->paginate(3, 1);
        $this->assertSame(10, $p->total());
        $this->assertSame(4, $p->lastPage());
    }

    /**
     * @return void
     */
    public function test_eqb_paginate_second_page_hydrates_entities(): void
    {
        $p = $this->items->query()->paginate(4, 2);
        $this->assertCount(4, $p->items());
        $this->assertSame('item-5', $p->items()[0]->name);
    }

    // ── EntityQueryBuilder::chunk() ──────────────────────────────────────────

    /**
     * @return void
     */
    public function test_eqb_chunk_visits_all_entities(): void
    {
        $names = [];
        $this->items->query()->chunk(4, function (array $entities) use (&$names): void {
            foreach ($entities as $entity) {
                $names[] = $entity->name;
            }
        });

        $this->assertCount(10, $names);
    }

    /**
     * @return void
     */
    public function test_eqb_chunk_calls_callback_per_chunk(): void
    {
        $calls = 0;
        $this->items->query()->chunk(4, function (array $entities) use (&$calls): void {
            $calls++;
        });

        $this->assertSame(3, $calls); // 4+4+2
    }

    // ── paginate() input validation ───────────────────────────────────────────

    /**
     * @return void
     */
    public function test_qb_paginate_throws_when_per_page_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'items'))->paginate(0, 1);
    }

    /**
     * @return void
     */
    public function test_qb_paginate_throws_when_per_page_is_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'items'))->paginate(-5, 1);
    }
}

/**
 * @internal Test entity stub mapped to the `items` table.
 *
 * @property string $name
 */
final class PaginationItem extends Entity
{
    protected static string $table = 'items';

    /** @var list<string> */
    protected static array $fillable = ['name'];
}

/**
 * @internal
 *
 * @extends AbstractRepository<PaginationItem>
 */
final class PaginationItemRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return PaginationItem::class;
    }
}
