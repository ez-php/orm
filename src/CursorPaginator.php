<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use InvalidArgumentException;

/**
 * Class CursorPaginator
 *
 * Immutable page of a keyset-paginated query (QueryBuilder::cursorPaginate()):
 * the items and an opaque cursor for the next page.
 *
 * The cursor is base64url-encoded JSON holding the key column and the last key.
 * It is not signed: a client can forge one, but only to start from another key
 * value of the same query, whose WHERE clauses still apply.
 *
 * @template T
 *
 * @package EzPhp\Orm
 */
final readonly class CursorPaginator
{
    /**
     * @param list<T>     $items
     * @param int         $perPage
     * @param string|null $nextCursor Cursor of the next page; null on the last page.
     */
    public function __construct(
        private array $items,
        private int $perPage,
        private ?string $nextCursor,
    ) {
    }

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * @return string|null
     */
    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * @return bool
     */
    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * @param string $column Key column the cursor belongs to.
     * @param mixed  $value  Last key of the page.
     *
     * @return string
     */
    public static function encode(string $column, mixed $value): string
    {
        return rtrim(strtr(base64_encode(json_encode(['c' => $column, 'v' => $value], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @param string $cursor
     * @param string $column Key column of the query; a cursor issued for another one is rejected.
     *
     * @return int|float|string The key to continue after.
     * @throws InvalidArgumentException When the cursor is malformed or belongs to another column.
     */
    public static function decode(string $cursor, string $column): int|float|string
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;

        if (
            !is_array($data)
            || ($data['c'] ?? null) !== $column
            || !isset($data['v'])
            || !(is_int($data['v']) || is_float($data['v']) || is_string($data['v']))
        ) {
            throw new InvalidArgumentException('Malformed pagination cursor.');
        }

        return $data['v'];
    }
}
