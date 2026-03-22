<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class PaginatorTest
 *
 * Unit tests for Paginator — all computed properties, edge cases (empty result,
 * last page with partial fill, single-page result).
 *
 * @package Tests
 */
#[CoversClass(Paginator::class)]
final class PaginatorTest extends TestCase
{
    // ── items ─────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_items_returns_given_items(): void
    {
        $p = new Paginator([['id' => 1], ['id' => 2]], 10, 5, 1);
        $this->assertSame([['id' => 1], ['id' => 2]], $p->items());
    }

    // ── basic accessors ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_total_returns_given_total(): void
    {
        $p = new Paginator([], 42, 15, 1);
        $this->assertSame(42, $p->total());
    }

    /**
     * @return void
     */
    public function test_per_page_returns_given_per_page(): void
    {
        $p = new Paginator([], 42, 15, 1);
        $this->assertSame(15, $p->perPage());
    }

    /**
     * @return void
     */
    public function test_current_page_returns_given_page(): void
    {
        $p = new Paginator([], 42, 15, 3);
        $this->assertSame(3, $p->currentPage());
    }

    // ── lastPage ──────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_last_page_is_ceil_of_total_over_per_page(): void
    {
        $p = new Paginator([], 31, 10, 1);
        $this->assertSame(4, $p->lastPage()); // ceil(31/10) = 4
    }

    /**
     * @return void
     */
    public function test_last_page_is_1_when_total_equals_per_page(): void
    {
        $p = new Paginator([], 10, 10, 1);
        $this->assertSame(1, $p->lastPage());
    }

    /**
     * @return void
     */
    public function test_last_page_is_at_least_1_when_total_is_zero(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertSame(1, $p->lastPage());
    }

    // ── hasMorePages ──────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_has_more_pages_is_true_when_not_on_last_page(): void
    {
        $p = new Paginator([], 30, 10, 2);
        $this->assertTrue($p->hasMorePages()); // lastPage = 3, currentPage = 2
    }

    /**
     * @return void
     */
    public function test_has_more_pages_is_false_on_last_page(): void
    {
        $p = new Paginator([], 30, 10, 3);
        $this->assertFalse($p->hasMorePages());
    }

    /**
     * @return void
     */
    public function test_has_more_pages_is_false_when_total_is_zero(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertFalse($p->hasMorePages());
    }

    // ── firstItem / lastItem ──────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_first_item_is_1_on_first_page(): void
    {
        $p = new Paginator([['id' => 1]], 5, 3, 1);
        $this->assertSame(1, $p->firstItem());
    }

    /**
     * @return void
     */
    public function test_first_item_is_offset_based_on_page(): void
    {
        $p = new Paginator([['id' => 4]], 5, 3, 2);
        $this->assertSame(4, $p->firstItem()); // (2-1)*3 + 1 = 4
    }

    /**
     * @return void
     */
    public function test_first_item_is_null_when_items_empty(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertNull($p->firstItem());
    }

    /**
     * @return void
     */
    public function test_last_item_is_first_item_plus_count_minus_one(): void
    {
        $p = new Paginator([['id' => 1], ['id' => 2]], 5, 3, 1);
        $this->assertSame(2, $p->lastItem()); // 1 + 2 - 1 = 2
    }

    /**
     * @return void
     */
    public function test_last_item_on_partial_last_page(): void
    {
        // Page 2, 3-per-page, 1 item on this page → firstItem = 4, lastItem = 4
        $p = new Paginator([['id' => 99]], 4, 3, 2);
        $this->assertSame(4, $p->lastItem());
    }

    /**
     * @return void
     */
    public function test_last_item_is_null_when_items_empty(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertNull($p->lastItem());
    }

    // ── isFirstPage ───────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_is_first_page_is_true_on_page_1(): void
    {
        $p = new Paginator([], 30, 10, 1);
        $this->assertTrue($p->isFirstPage());
    }

    /**
     * @return void
     */
    public function test_is_first_page_is_false_on_page_2(): void
    {
        $p = new Paginator([], 30, 10, 2);
        $this->assertFalse($p->isFirstPage());
    }

    // ── isLastPage ────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_is_last_page_is_true_on_last_page(): void
    {
        $p = new Paginator([], 30, 10, 3);
        $this->assertTrue($p->isLastPage());
    }

    /**
     * @return void
     */
    public function test_is_last_page_is_false_before_last_page(): void
    {
        $p = new Paginator([], 30, 10, 2);
        $this->assertFalse($p->isLastPage());
    }

    /**
     * @return void
     */
    public function test_is_last_page_is_true_when_total_is_zero(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertTrue($p->isLastPage()); // lastPage = 1, currentPage = 1
    }

    // ── from / to ─────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_from_is_alias_for_first_item(): void
    {
        $p = new Paginator([['id' => 4]], 5, 3, 2);
        $this->assertSame($p->firstItem(), $p->from());
    }

    /**
     * @return void
     */
    public function test_from_is_null_when_items_empty(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertNull($p->from());
    }

    /**
     * @return void
     */
    public function test_to_is_alias_for_last_item(): void
    {
        $p = new Paginator([['id' => 1], ['id' => 2]], 5, 3, 1);
        $this->assertSame($p->lastItem(), $p->to());
    }

    /**
     * @return void
     */
    public function test_to_is_null_when_items_empty(): void
    {
        $p = new Paginator([], 0, 15, 1);
        $this->assertNull($p->to());
    }

    // ── constructor validation ────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_constructor_throws_when_page_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Paginator([], 0, 15, 0);
    }

    /**
     * @return void
     */
    public function test_constructor_throws_when_page_is_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Paginator([], 0, 15, -1);
    }

    /**
     * @return void
     */
    public function test_constructor_throws_when_per_page_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Paginator([], 0, 0, 1);
    }

    /**
     * @return void
     */
    public function test_constructor_throws_when_per_page_is_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Paginator([], 0, -1, 1);
    }
}
