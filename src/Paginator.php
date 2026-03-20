<?php

declare(strict_types=1);

namespace EzPhp\Orm;

/**
 * Class Paginator
 *
 * Immutable value object that wraps a page of results together with
 * the metadata needed to render pagination controls.
 *
 * @template T
 *
 * @package EzPhp\Orm
 */
final class Paginator
{
    /**
     * @param list<T> $items
     * @param int     $total
     * @param int     $perPage
     * @param int     $currentPage
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
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
    public function total(): int
    {
        return $this->total;
    }

    /**
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * @return int
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * @return int
     */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * 1-based index of the first item on this page, or null when there are no items.
     *
     * @return int|null
     */
    public function firstItem(): ?int
    {
        if ($this->items === []) {
            return null;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * 1-based index of the last item on this page, or null when there are no items.
     *
     * @return int|null
     */
    public function lastItem(): ?int
    {
        $first = $this->firstItem();

        if ($first === null) {
            return null;
        }

        return $first + count($this->items) - 1;
    }

    /**
     * Return true when the current page is the first page.
     *
     * @return bool
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Return true when the current page is the last page.
     *
     * @return bool
     */
    public function isLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage();
    }

    /**
     * Alias for firstItem() — 1-based index of the first item on this page.
     *
     * @return int|null
     */
    public function from(): ?int
    {
        return $this->firstItem();
    }

    /**
     * Alias for lastItem() — 1-based index of the last item on this page.
     *
     * @return int|null
     */
    public function to(): ?int
    {
        return $this->lastItem();
    }
}
