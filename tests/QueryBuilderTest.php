<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class QueryBuilderTest
 *
 * @package Tests\Database
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderTest extends TestCase
{
    private PdoDatabase $db;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new PdoDatabase('sqlite::memory:');
        $this->db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, score REAL DEFAULT NULL, email TEXT DEFAULT NULL)');
        $this->db->query("INSERT INTO users (name, active, score) VALUES ('Alice', 1, 10.0)");
        $this->db->query("INSERT INTO users (name, active, score) VALUES ('Bob', 0, 20.0)");
        $this->db->query("INSERT INTO users (name, active, score) VALUES ('Charlie', 1, 30.0)");
        $this->db->query('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount REAL NOT NULL)');
        $this->db->query('INSERT INTO orders (user_id, amount) VALUES (1, 100.0)');
        $this->db->query('INSERT INTO orders (user_id, amount) VALUES (1, 50.0)');
        $this->db->query('INSERT INTO orders (user_id, amount) VALUES (2, 75.0)');
    }

    /**
     * @return void
     */
    public function test_get_returns_all_rows(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->get();

        $this->assertCount(3, $rows);
    }

    /**
     * @return void
     */
    public function test_where_filters_rows(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->where('active', 1)->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_where_with_operator(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->where('id', '>', 1)->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_multiple_wheres_are_combined_with_and(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->where('active', 1)->where('name', 'Alice')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_select_limits_columns(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->select('name')->where('name', 'Alice')->get();

        $this->assertSame(['name' => 'Alice'], $rows[0]);
    }

    /**
     * @return void
     */
    public function test_select_with_no_args_returns_all_columns(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->select()->where('name', 'Alice')->get();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);
    }

    /**
     * @return void
     */
    public function test_order_by_asc(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('name')->get();

        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame('Charlie', $rows[2]['name']);
    }

    /**
     * @return void
     */
    public function test_order_by_desc(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('name', 'desc')->get();

        $this->assertSame('Charlie', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_limit_restricts_result_count(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->limit(2)->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_offset_skips_rows(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('id')->offset(2)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Charlie', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_first_returns_single_row(): void
    {
        $row = (new QueryBuilder($this->db, 'users'))->orderBy('name')->first();

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    /**
     * @return void
     */
    public function test_first_returns_null_when_no_rows(): void
    {
        $row = (new QueryBuilder($this->db, 'users'))->where('name', 'Nobody')->first();

        $this->assertNull($row);
    }

    /**
     * @return void
     */
    public function test_count_returns_total_row_count(): void
    {
        $count = (new QueryBuilder($this->db, 'users'))->count();

        $this->assertSame(3, $count);
    }

    /**
     * @return void
     */
    public function test_count_respects_where(): void
    {
        $count = (new QueryBuilder($this->db, 'users'))->where('active', 1)->count();

        $this->assertSame(2, $count);
    }

    /**
     * @return void
     */
    public function test_insert_adds_a_row(): void
    {
        $result = (new QueryBuilder($this->db, 'users'))->insert(['name' => 'Dave', 'active' => 1]);

        $this->assertTrue($result);
        $this->assertSame(4, (new QueryBuilder($this->db, 'users'))->count());
    }

    /**
     * @return void
     */
    public function test_update_modifies_matching_rows(): void
    {
        $affected = (new QueryBuilder($this->db, 'users'))->where('name', 'Bob')->update(['active' => 1]);

        $this->assertSame(1, $affected);
        $this->assertSame(3, (new QueryBuilder($this->db, 'users'))->where('active', 1)->count());
    }

    /**
     * @return void
     */
    public function test_delete_removes_matching_rows(): void
    {
        $affected = (new QueryBuilder($this->db, 'users'))->where('active', 0)->delete();

        $this->assertSame(1, $affected);
        $this->assertSame(2, (new QueryBuilder($this->db, 'users'))->count());
    }

    /**
     * @return void
     */
    public function test_builder_is_immutable(): void
    {
        $base = (new QueryBuilder($this->db, 'users'));
        $filtered = $base->where('active', 1);

        $this->assertSame(3, $base->count());
        $this->assertSame(2, $filtered->count());
    }

    // --- orWhere ---

    /**
     * @return void
     */
    public function test_or_where_returns_results_from_both_conditions(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->where('name', 'Alice')
            ->orWhere('name', 'Bob')
            ->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_or_where_with_operator(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->where('active', 0)
            ->orWhere('score', '>', 25.0)
            ->get();

        // Bob (active=0) + Charlie (score=30 > 25) = 2
        $this->assertCount(2, $rows);
    }

    // --- whereIn / whereNotIn ---

    /**
     * @return void
     */
    public function test_where_in_filters_by_included_values(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->whereIn('id', [1, 3])->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_where_not_in_excludes_values(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->whereNotIn('id', [1])->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_where_in_combined_with_where(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->where('active', 1)
            ->whereIn('id', [1, 2, 3])
            ->get();

        // active=1: Alice (id=1), Charlie (id=3) — both in [1,2,3]
        $this->assertCount(2, $rows);
    }

    // --- whereNull / whereNotNull ---

    /**
     * @return void
     */
    public function test_where_null_finds_rows_with_null_column(): void
    {
        // All users have NULL email by default
        $rows = (new QueryBuilder($this->db, 'users'))->whereNull('email')->get();

        $this->assertCount(3, $rows);
    }

    /**
     * @return void
     */
    public function test_where_not_null_finds_rows_with_non_null_column(): void
    {
        (new QueryBuilder($this->db, 'users'))->where('name', 'Alice')->update(['email' => 'alice@example.com']);
        $rows = (new QueryBuilder($this->db, 'users'))->whereNotNull('email')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // --- JOIN ---

    /**
     * @return void
     */
    public function test_join_combines_rows_from_two_tables(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->select('users.name', 'orders.amount')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->orderBy('orders.amount')
            ->get();

        // Alice has 2 orders (100, 50), Bob has 1 order (75), Charlie has none → 3 rows
        $this->assertCount(3, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertEquals(50.0, $rows[0]['amount']);
    }

    /**
     * @return void
     */
    public function test_left_join_includes_rows_without_match(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->select('users.name', 'orders.amount')
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->get();

        // Alice: 2 rows, Bob: 1 row, Charlie: 1 row (NULL amount) = 4 rows
        $this->assertCount(4, $rows);
    }

    /**
     * @return void
     */
    public function test_join_with_where_filters_correctly(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->select('users.name', 'orders.amount')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.amount', '>', 60.0)
            ->get();

        // Only Alice's 100 and Bob's 75 exceed 60
        $this->assertCount(2, $rows);
    }

    // --- GROUP BY / HAVING ---

    /**
     * @return void
     */
    public function test_group_by_groups_results(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->select('active')
            ->groupBy('active')
            ->orderBy('active')
            ->get();

        $this->assertCount(2, $rows);
    }

    /**
     * @return void
     */
    public function test_having_filters_groups(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->select('active', 'COUNT(*) as cnt')
            ->groupBy('active')
            ->having('COUNT(*)', '>', 1)
            ->get();

        // Only active=1 group has count > 1 (Alice + Charlie)
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['active']);
    }

    /**
     * @return void
     */
    public function test_group_by_multiple_columns(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))
            ->select('active', 'name')
            ->groupBy('active', 'name')
            ->orderBy('name')
            ->get();

        $this->assertCount(3, $rows);
    }

    // --- Aggregate functions ---

    /**
     * @return void
     */
    public function test_sum_returns_total_of_column(): void
    {
        $sum = (new QueryBuilder($this->db, 'users'))->sum('score');

        $this->assertSame(60.0, $sum);
    }

    /**
     * @return void
     */
    public function test_sum_respects_where(): void
    {
        $sum = (new QueryBuilder($this->db, 'users'))->where('active', 1)->sum('score');

        // Alice (10) + Charlie (30) = 40
        $this->assertSame(40.0, $sum);
    }

    /**
     * @return void
     */
    public function test_avg_returns_average_of_column(): void
    {
        $avg = (new QueryBuilder($this->db, 'users'))->avg('score');

        $this->assertSame(20.0, $avg);
    }

    /**
     * @return void
     */
    public function test_min_returns_minimum_of_column(): void
    {
        $min = (new QueryBuilder($this->db, 'users'))->min('score');

        $this->assertSame(10.0, (float) $min);
    }

    /**
     * @return void
     */
    public function test_max_returns_maximum_of_column(): void
    {
        $max = (new QueryBuilder($this->db, 'users'))->max('score');

        $this->assertSame(30.0, (float) $max);
    }

    /**
     * @return void
     */
    public function test_min_with_where_filter(): void
    {
        $min = (new QueryBuilder($this->db, 'users'))->where('active', 0)->min('score');

        $this->assertSame(20.0, (float) $min);
    }
}
