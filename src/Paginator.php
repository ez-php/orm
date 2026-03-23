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
final readonly class Paginator
{
    /**
     * @param list<T> $items
     * @param int     $total
     * @param int     $perPage
     * @param int     $currentPage
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $perPage,
        private int $currentPage,
    ) {
        if ($perPage < 1) {
            throw new \InvalidArgumentException("perPage must be >= 1, got $perPage.");
        }

        if ($currentPage < 1) {
            throw new \InvalidArgumentException("currentPage must be >= 1, got $currentPage.");
        }
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

    /**
     * Build a URL for the given page number.
     *
     * Appends `?param=page` (or `&param=page` when the base URL already
     * contains a query string).
     *
     * @param string $baseUrl Base URL without the page parameter.
     * @param int    $page    Target page number.
     * @param string $param   Query parameter name (default: 'page').
     *
     * @return string
     */
    public function urlForPage(string $baseUrl, int $page, string $param = 'page'): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . urlencode($param) . '=' . $page;
    }

    /**
     * Return prev/next and full page URL set for rendering pagination controls.
     *
     * @param string $baseUrl Base URL without the page parameter.
     * @param string $param   Query parameter name (default: 'page').
     *
     * @return array{prev: string|null, next: string|null, pages: list<string>}
     */
    public function links(string $baseUrl, string $param = 'page'): array
    {
        $prev = $this->currentPage > 1
            ? $this->urlForPage($baseUrl, $this->currentPage - 1, $param)
            : null;

        $next = $this->hasMorePages()
            ? $this->urlForPage($baseUrl, $this->currentPage + 1, $param)
            : null;

        $pages = [];
        for ($i = 1; $i <= $this->lastPage(); $i++) {
            $pages[] = $this->urlForPage($baseUrl, $i, $param);
        }

        return ['prev' => $prev, 'next' => $next, 'pages' => $pages];
    }
}
