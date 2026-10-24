<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Hydrator;
use EzPhp\Orm\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Keyset iteration: QueryBuilder/EntityQueryBuilder::chunkById() and lazy().
 * SQLite table with 10 rows; rows 3 and 8 are soft-deleted.
 */
#[CoversClass(QueryBuilder::class)]
#[CoversClass(EntityQueryBuilder::class)]
#[UsesClass(Entity::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(Hydrator::class)]
final class KeysetIterationTest extends RepositoryTestCase
{
    private KeysetItemRepository $items;

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE keyset_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, grp TEXT NOT NULL, deleted_at TEXT NULL)');
        for ($i = 1; $i <= 10; $i++) {
            $grp = $i % 2 === 0 ? 'even' : 'odd';
            $deleted = in_array($i, [3, 8], true) ? "'2026-01-01 00:00:00'" : 'NULL';
            $this->exec("INSERT INTO keyset_items (name, grp, deleted_at) VALUES ('item-$i', '$grp', $deleted)");
        }

        $this->items = new KeysetItemRepository($this->db, $this->hydrator);
    }

    private function qb(): QueryBuilder
    {
        return new QueryBuilder($this->db, 'keyset_items');
    }

    public function testChunkByIdVisitsEveryRowOnceInKeyOrder(): void
    {
        $pages = [];
        $this->qb()->orderBy('name', 'DESC')->limit(2)->offset(5)
            ->chunkById(4, static function (array $rows) use (&$pages): void {
                $pages[] = array_column($rows, 'id');
            });

        $this->assertSame([[1, 2, 3, 4], [5, 6, 7, 8], [9, 10]], $pages);
    }

    public function testChunkByIdIsStableWhenTheCallbackDeletesRows(): void
    {
        $seen = [];
        $qb = $this->qb();
        $qb->chunkById(3, function (array $rows) use (&$seen, $qb): void {
            foreach ($rows as $row) {
                $seen[] = $row['id'];
                $qb->where('id', $row['id'])->delete();
            }
        });

        // OFFSET paging would skip rows here, because each deletion shifts the next page.
        $this->assertSame(range(1, 10), $seen);
    }

    public function testOrWhereCannotEscapeTheCursor(): void
    {
        $ids = [];
        $this->qb()->where('grp', 'odd')->orWhere('id', 2)
            ->chunkById(2, static function (array $rows) use (&$ids): void {
                array_push($ids, ...array_column($rows, 'id'));
            });

        $this->assertSame([1, 2, 3, 5, 7, 9], $ids);
    }

    public function testLazyStreamsRowsAndDoesNotChangeTheSourceBuilder(): void
    {
        $qb = $this->qb()->where('grp', 'even');

        $this->assertSame([2, 4, 6, 8, 10], array_column(iterator_to_array($qb->lazy(2), false), 'id'));
        $this->assertSame('SELECT * FROM keyset_items WHERE `grp` = ?', $qb->toSql());
    }

    public function testQualifiedColumnUsesItsLastSegmentAsRowKey(): void
    {
        $ids = array_column(iterator_to_array($this->qb()->lazy(3, 'keyset_items.id'), false), 'id');

        $this->assertSame(range(1, 10), $ids);
    }

    public function testMissingKeyColumnThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Keyset column 'id' is not part of the selected columns.");

        $this->qb()->select('name')->chunkById(3, static function (): void {
        });
    }

    public function testSizeMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($this->qb()->lazy(0));
    }

    public function testEntityChunkByIdKeepsTheSoftDeleteFilter(): void
    {
        $pages = [];
        $this->items->query()->chunkById(3, static function (array $entities) use (&$pages): void {
            $pages[] = array_map(static fn (KeysetItem $e): mixed => $e->getAttribute('id'), $entities);
        });

        $this->assertSame([[1, 2, 4], [5, 6, 7], [9, 10]], $pages);
    }

    public function testEntityLazyYieldsHydratedEntities(): void
    {
        $names = [];
        foreach ($this->items->query()->where('grp', 'odd')->lazy(2) as $entity) {
            $this->assertInstanceOf(KeysetItem::class, $entity);
            $names[] = $entity->getAttribute('name');
        }

        $this->assertSame(['item-1', 'item-5', 'item-7', 'item-9'], $names);
    }
}

/**
 * @internal Soft-deleting test entity mapped to `keyset_items`.
 */
final class KeysetItem extends Entity
{
    protected static string $table = 'keyset_items';

    /** @var list<string> */
    protected static array $fillable = ['name', 'grp'];

    protected static bool $softDeletes = true;
}

/**
 * @internal
 *
 * @extends AbstractRepository<KeysetItem>
 */
final class KeysetItemRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return KeysetItem::class;
    }
}
