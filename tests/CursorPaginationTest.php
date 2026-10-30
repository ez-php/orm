<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\CursorPaginator;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Hydrator;
use EzPhp\Orm\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Opaque cursor pagination: QueryBuilder/EntityQueryBuilder::cursorPaginate().
 * SQLite table with 10 rows; rows 3 and 8 are soft-deleted.
 */
#[CoversClass(CursorPaginator::class)]
#[CoversClass(QueryBuilder::class)]
#[CoversClass(EntityQueryBuilder::class)]
#[UsesClass(Entity::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(Hydrator::class)]
final class CursorPaginationTest extends RepositoryTestCase
{
    private CursorItemRepository $items;

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE cursor_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, deleted_at TEXT NULL)');

        for ($i = 1; $i <= 10; $i++) {
            $deleted = in_array($i, [3, 8], true) ? "'2026-01-01 00:00:00'" : 'NULL';
            $this->exec("INSERT INTO cursor_items (name, deleted_at) VALUES ('item-$i', $deleted)");
        }

        $this->items = new CursorItemRepository($this->db, $this->hydrator);
    }

    private function qb(): QueryBuilder
    {
        return new QueryBuilder($this->db, 'cursor_items');
    }

    public function testFirstPageReturnsARowsAndCursor(): void
    {
        $page = $this->qb()->orderBy('name', 'DESC')->cursorPaginate(4);

        $this->assertSame([1, 2, 3, 4], array_column($page->items(), 'id'));
        $this->assertSame(4, $page->perPage());
        $this->assertTrue($page->hasMore());
        $this->assertNotNull($page->nextCursor());
    }

    public function testFollowingTheCursorVisitsEveryRowOnce(): void
    {
        $seen = [];
        $cursor = null;

        do {
            $page = $this->qb()->cursorPaginate(4, $cursor);
            $seen = [...$seen, ...array_column($page->items(), 'id')];
            $cursor = $page->nextCursor();
        } while ($cursor !== null);

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $seen);
    }

    public function testAnExactlyFullLastPageHasNoCursor(): void
    {
        $page = $this->qb()->cursorPaginate(10);

        $this->assertCount(10, $page->items());
        $this->assertFalse($page->hasMore());
        $this->assertNull($page->nextCursor());
    }

    public function testRowsDeletedBetweenRequestsDoNotShiftLaterPages(): void
    {
        $first = $this->qb()->cursorPaginate(4);
        $this->exec('DELETE FROM cursor_items WHERE id IN (1, 2)');

        $second = $this->qb()->cursorPaginate(4, $first->nextCursor());

        $this->assertSame([5, 6, 7, 8], array_column($second->items(), 'id'));
    }

    public function testWhereClausesAreKept(): void
    {
        $page = $this->qb()->where('id', '>', 8)->cursorPaginate(5);

        $this->assertSame([9, 10], array_column($page->items(), 'id'));
    }

    public function testEntityBuilderHydratesAndKeepsTheSoftDeleteFilter(): void
    {
        $first = $this->items->query()->cursorPaginate(3);
        $second = $this->items->query()->cursorPaginate(3, $first->nextCursor());

        $ids = static function (CursorPaginator $page): array {
            /** @var list<CursorItem> $items */
            $items = $page->items();

            return array_map(static fn (CursorItem $item): mixed => $item->getKey(), $items);
        };

        $this->assertSame([1, 2, 4], $ids($first));
        $this->assertSame([5, 6, 7], $ids($second));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badCursors(): array
    {
        return [
            'not base64' => ['***'],
            'not json' => [rtrim(strtr(base64_encode('nope'), '+/', '-_'), '=')],
            'no value' => [rtrim(strtr(base64_encode('{"c":"id"}'), '+/', '-_'), '=')],
            'nested value' => [rtrim(strtr(base64_encode('{"c":"id","v":[1]}'), '+/', '-_'), '=')],
            'other column' => [rtrim(strtr(base64_encode('{"c":"name","v":1}'), '+/', '-_'), '=')],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badCursors')]
    public function testAMalformedCursorIsRejected(string $cursor): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->qb()->cursorPaginate(4, $cursor);
    }

    public function testPerPageMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->qb()->cursorPaginate(0);
    }

    public function testAColumnThatIsNotSelectedIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->qb()->select('name')->cursorPaginate(4);
    }
}

/**
 * @internal
 */
final class CursorItem extends Entity
{
    protected static string $table = 'cursor_items';

    /** @var list<string> */
    protected static array $fillable = ['name'];

    protected static bool $softDeletes = true;
}

/**
 * @internal
 *
 * @extends AbstractRepository<CursorItem>
 */
final class CursorItemRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return CursorItem::class;
    }
}
