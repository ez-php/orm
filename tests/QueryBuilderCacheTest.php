<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Cache\ArrayDriver;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class QueryBuilderCacheTest
 *
 * Tests QueryBuilder result caching via the cache() method.
 * Uses an in-memory SQLite database and ArrayDriver.
 *
 * @package Tests
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderCacheTest extends TestCase
{
    private PdoDatabase $db;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new PdoDatabase('sqlite::memory:');
        $this->db->query('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->db->query("INSERT INTO products (name) VALUES ('Widget')");
        $this->db->query("INSERT INTO products (name) VALUES ('Gadget')");
    }

    /**
     * @return void
     */
    public function test_cache_get_returns_results_and_caches_them(): void
    {
        $driver = new ArrayDriver();
        $rows = (new QueryBuilder($this->db, 'products'))
            ->cache(60, $driver)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame('Widget', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_cache_second_call_returns_from_cache(): void
    {
        $driver = new ArrayDriver();

        $qb = (new QueryBuilder($this->db, 'products'))->cache(60, $driver);

        $rows1 = $qb->get();

        // Insert a new row — if cache works, the second call should NOT see this.
        $this->db->query("INSERT INTO products (name) VALUES ('Doohickey')");

        $rows2 = $qb->get();

        // First call: 2 rows; second call must return the cached 2-row result.
        $this->assertCount(2, $rows1);
        $this->assertCount(2, $rows2);

        // The driver should have recorded exactly one miss (the first call).
        $stats = $driver->stats();
        $this->assertSame(1, $stats->misses);
        $this->assertSame(1, $stats->hits);
    }

    /**
     * @return void
     */
    public function test_get_without_cache_returns_results_normally(): void
    {
        $rows = (new QueryBuilder($this->db, 'products'))->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_cache_is_keyed_by_sql_and_bindings(): void
    {
        $driver = new ArrayDriver();

        $rows1 = (new QueryBuilder($this->db, 'products'))
            ->where('name', 'Widget')
            ->cache(60, $driver)
            ->get();

        $rows2 = (new QueryBuilder($this->db, 'products'))
            ->where('name', 'Gadget')
            ->cache(60, $driver)
            ->get();

        $this->assertCount(1, $rows1);
        $this->assertCount(1, $rows2);
        $this->assertSame('Widget', $rows1[0]['name']);
        $this->assertSame('Gadget', $rows2[0]['name']);
    }
}
