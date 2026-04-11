<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\QueryBuilder;
use InvalidArgumentException;
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
        $this->db->query('CREATE TABLE items (id INTEGER PRIMARY KEY, tags TEXT NOT NULL)');
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
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('id')->limit(10)->offset(2)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Charlie', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_offset_without_limit_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OFFSET requires an explicit LIMIT');

        (new QueryBuilder($this->db, 'users'))->offset(5)->get();
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

    // =========================================================================
    // Feature 3: Upsert
    // =========================================================================

    /**
     * @return void
     */
    public function test_upsert_inserts_new_row(): void
    {
        $before = (new QueryBuilder($this->db, 'users'))->count();
        (new QueryBuilder($this->db, 'users'))->upsert(
            ['id' => 99, 'name' => 'Dave', 'active' => 1],
            ['id'],
            ['name', 'active']
        );

        $this->assertSame($before + 1, (new QueryBuilder($this->db, 'users'))->count());
    }

    /**
     * @return void
     */
    public function test_upsert_updates_on_conflict(): void
    {
        // id=1 already exists (Alice)
        (new QueryBuilder($this->db, 'users'))->upsert(
            ['id' => 1, 'name' => 'AliceUpdated', 'active' => 0],
            ['id'],
            ['name', 'active']
        );

        $row = (new QueryBuilder($this->db, 'users'))->where('id', 1)->first();
        $this->assertNotNull($row);
        $this->assertSame('AliceUpdated', $row['name']);
    }

    /**
     * @return void
     */
    public function test_upsert_throws_on_empty_data(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'users'))->upsert([], ['id']);
    }

    /**
     * @return void
     */
    public function test_upsert_throws_on_empty_unique_by(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'users'))->upsert(['name' => 'Test', 'active' => 1], []);
    }

    // =========================================================================
    // Feature 7: Batch Insert
    // =========================================================================

    /**
     * @return void
     */
    public function test_insert_batch_inserts_multiple_rows(): void
    {
        (new QueryBuilder($this->db, 'users'))->insertBatch([
            ['name' => 'Dave', 'active' => 1],
            ['name' => 'Eve', 'active' => 0],
        ]);

        $this->assertSame(5, (new QueryBuilder($this->db, 'users'))->count());
    }

    /**
     * @return void
     */
    public function test_insert_batch_single_row_works(): void
    {
        (new QueryBuilder($this->db, 'users'))->insertBatch([
            ['name' => 'Dave', 'active' => 1],
        ]);

        $this->assertSame(4, (new QueryBuilder($this->db, 'users'))->count());
    }

    /**
     * @return void
     */
    public function test_insert_batch_throws_on_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'users'))->insertBatch([]);
    }

    /**
     * @return void
     */
    public function test_insert_batch_throws_on_mismatched_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'users'))->insertBatch([
            ['name' => 'Dave', 'active' => 1],
            ['name' => 'Eve'],
        ]);
    }

    // =========================================================================
    // Feature 6: Subquery support
    // =========================================================================

    /**
     * @return void
     */
    public function test_where_subquery_filters_results(): void
    {
        // Get users whose id is in a subquery returning ids with score > 15
        $subQb = (new QueryBuilder($this->db, 'users'))->select('id')->where('score', '>', 15.0);
        $rows = (new QueryBuilder($this->db, 'users'))->where('id', $subQb)->get();

        // Bob (score=20) and Charlie (score=30) qualify
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Bob', $names);
        $this->assertContains('Charlie', $names);
    }

    /**
     * @return void
     */
    public function test_from_sub_uses_derived_table(): void
    {
        // Wrap users in a derived table and count rows
        $subQb = (new QueryBuilder($this->db, 'users'))->select('id', 'name')->where('active', 1);
        $count = (new QueryBuilder($this->db, 'users'))
            ->fromSub($subQb, 'active_users')
            ->count();

        // count() uses the table name directly, not fromSub; test via get()
        $rows = (new QueryBuilder($this->db, 'users'))
            ->fromSub($subQb, 'active_users')
            ->get();

        $this->assertCount(2, $rows);

        // Suppress phpstan warning about $count not being used
        $this->assertSame(2, $count);
    }

    // =========================================================================
    // Feature 13: whereJsonContains
    // =========================================================================

    /**
     * @return void
     */
    public function test_where_json_contains_finds_row(): void
    {
        $this->db->query("INSERT INTO items (tags) VALUES ('[\"php\",\"laravel\"]')");
        $this->db->query("INSERT INTO items (tags) VALUES ('[\"go\",\"rust\"]')");

        $rows = (new QueryBuilder($this->db, 'items'))->whereJsonContains('tags', 'php')->get();

        $this->assertCount(1, $rows);
    }

    /**
     * @return void
     */
    public function test_where_json_contains_no_match(): void
    {
        $this->db->query("INSERT INTO items (tags) VALUES ('[\"php\",\"laravel\"]')");

        $rows = (new QueryBuilder($this->db, 'items'))->whereJsonContains('tags', 'python')->get();

        $this->assertCount(0, $rows);
    }

    // =========================================================================
    // Feature 3: increment / decrement
    // =========================================================================

    /**
     * @return void
     */
    public function test_increment_increases_column_by_one(): void
    {
        (new QueryBuilder($this->db, 'users'))->where('name', 'Alice')->increment('score');

        $row = (new QueryBuilder($this->db, 'users'))->where('name', 'Alice')->first();
        $this->assertNotNull($row);
        $this->assertSame(11.0, $row['score']);
    }

    /**
     * @return void
     */
    public function test_increment_increases_column_by_custom_amount(): void
    {
        (new QueryBuilder($this->db, 'users'))->where('name', 'Alice')->increment('score', 5);

        $row = (new QueryBuilder($this->db, 'users'))->where('name', 'Alice')->first();
        $this->assertNotNull($row);
        $this->assertSame(15.0, $row['score']);
    }

    /**
     * @return void
     */
    public function test_decrement_decreases_column_by_one(): void
    {
        (new QueryBuilder($this->db, 'users'))->where('name', 'Bob')->decrement('score');

        $row = (new QueryBuilder($this->db, 'users'))->where('name', 'Bob')->first();
        $this->assertNotNull($row);
        $this->assertSame(19.0, $row['score']);
    }

    /**
     * @return void
     */
    public function test_decrement_decreases_column_by_custom_amount(): void
    {
        (new QueryBuilder($this->db, 'users'))->where('name', 'Charlie')->decrement('score', 10);

        $row = (new QueryBuilder($this->db, 'users'))->where('name', 'Charlie')->first();
        $this->assertNotNull($row);
        $this->assertSame(20.0, $row['score']);
    }

    /**
     * @return void
     */
    public function test_increment_without_where_affects_all_rows(): void
    {
        $affected = (new QueryBuilder($this->db, 'users'))->increment('score', 100);

        $this->assertSame(3, $affected);
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('id')->get();
        $this->assertSame(110.0, $rows[0]['score']);
        $this->assertSame(120.0, $rows[1]['score']);
        $this->assertSame(130.0, $rows[2]['score']);
    }

    /**
     * @return void
     */
    public function test_increment_returns_affected_row_count(): void
    {
        $affected = (new QueryBuilder($this->db, 'users'))->where('active', 1)->increment('score');

        $this->assertSame(2, $affected);
    }

    /**
     * @return void
     */
    public function test_decrement_returns_affected_row_count(): void
    {
        $affected = (new QueryBuilder($this->db, 'users'))->where('active', 0)->decrement('score');

        $this->assertSame(1, $affected);
    }

    // =========================================================================
    // Feature 14: whereExists / whereNotExists
    // =========================================================================

    /**
     * @return void
     */
    public function test_where_exists_returns_rows_with_matching_subquery(): void
    {
        // Alice (id=1) and Bob (id=2) have orders; Charlie (id=3) does not
        $rows = (new QueryBuilder($this->db, 'users'))
            ->whereExists('SELECT 1 FROM orders WHERE orders.user_id = users.id')
            ->get();

        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
    }

    /**
     * @return void
     */
    public function test_where_not_exists_excludes_rows_with_matching_subquery(): void
    {
        // Only Charlie (id=3) has no orders
        $rows = (new QueryBuilder($this->db, 'users'))
            ->whereNotExists('SELECT 1 FROM orders WHERE orders.user_id = users.id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Charlie', $rows[0]['name']);
    }

    // =========================================================================
    // Security: identifier validation
    // =========================================================================

    /**
     * @return void
     */
    public function test_order_by_throws_on_invalid_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ORDER BY direction');

        (new QueryBuilder($this->db, 'users'))->orderBy('name', 'INVALID');
    }

    /**
     * @return void
     */
    public function test_order_by_rejects_sql_injection_in_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryBuilder($this->db, 'users'))->orderBy('name', 'ASC; DROP TABLE users--');
    }

    /**
     * @return void
     */
    public function test_order_by_accepts_asc_case_insensitive(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('name', 'asc')->get();
        $this->assertSame('Alice', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_order_by_accepts_desc_case_insensitive(): void
    {
        $rows = (new QueryBuilder($this->db, 'users'))->orderBy('name', 'desc')->get();
        $this->assertSame('Charlie', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_invalid_column_in_order_by_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        (new QueryBuilder($this->db, 'users'))->orderBy('name; DROP TABLE users--')->get();
    }

    /**
     * @return void
     */
    public function test_invalid_column_in_group_by_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        (new QueryBuilder($this->db, 'users'))->groupBy('active; DROP TABLE users--')->get();
    }

    /**
     * @return void
     */
    public function test_invalid_column_in_join_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        (new QueryBuilder($this->db, 'users'))
            ->join('orders; DROP TABLE orders--', 'users.id', '=', 'orders.user_id')
            ->get();
    }

    /**
     * @return void
     */
    public function test_invalid_column_in_sum_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        (new QueryBuilder($this->db, 'users'))->sum('score; DROP TABLE users--');
    }

    /**
     * @return void
     */
    public function test_invalid_column_in_increment_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        (new QueryBuilder($this->db, 'users'))->increment('score; DROP TABLE users--');
    }
}
