<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\Paginator;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class PaginationTest
 *
 * Integration tests for QueryBuilder::paginate() / chunk() and the mirrored
 * methods on ModelQueryBuilder. Uses an in-memory SQLite database seeded with
 * 10 rows so pagination arithmetic is easy to verify.
 *
 * @package Tests
 */
#[CoversClass(QueryBuilder::class)]
#[CoversClass(ModelQueryBuilder::class)]
#[UsesClass(Paginator::class)]
#[UsesClass(Model::class)]
final class PaginationTest extends ModelTestCase
{
    /**
     * @return void
     */
    protected function setUpDatabase(): void
    {
        $this->db->query('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        for ($i = 1; $i <= 10; $i++) {
            $this->db->query("INSERT INTO items (name) VALUES ('item-$i')");
        }
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
        $this->db->query('DELETE FROM items');
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

    // ── ModelQueryBuilder::paginate() ────────────────────────────────────────

    /**
     * @return void
     */
    public function test_mqb_paginate_returns_paginator_of_models(): void
    {
        $p = PaginationItem::query()->paginate(3, 1);
        $this->assertInstanceOf(Paginator::class, $p);
        $this->assertInstanceOf(PaginationItem::class, $p->items()[0]);
    }

    /**
     * @return void
     */
    public function test_mqb_paginate_total_and_last_page(): void
    {
        $p = PaginationItem::query()->paginate(3, 1);
        $this->assertSame(10, $p->total());
        $this->assertSame(4, $p->lastPage());
    }

    /**
     * @return void
     */
    public function test_mqb_paginate_second_page_hydrates_models(): void
    {
        $p = PaginationItem::query()->paginate(4, 2);
        $this->assertCount(4, $p->items());
        $this->assertSame('item-5', $p->items()[0]->name);
    }

    // ── ModelQueryBuilder::chunk() ───────────────────────────────────────────

    /**
     * @return void
     */
    public function test_mqb_chunk_visits_all_models(): void
    {
        $names = [];
        PaginationItem::query()->chunk(4, function (array $models) use (&$names): void {
            foreach ($models as $model) {
                $names[] = $model->name;
            }
        });

        $this->assertCount(10, $names);
    }

    /**
     * @return void
     */
    public function test_mqb_chunk_calls_callback_per_chunk(): void
    {
        $calls = 0;
        PaginationItem::query()->chunk(4, function (array $models) use (&$calls): void {
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
 * Class PaginationItem
 *
 * Test model stub mapped to the `items` table.
 *
 * @package Tests
 *
 * @property string $name
 */
class PaginationItem extends Model
{
    /** @var string */
    protected static string $table = 'items';
}
