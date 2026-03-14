<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

/**
 * Class ForeignKeyDefinition
 *
 * @package EzPhp\Orm\Schema
 */
final class ForeignKeyDefinition
{
    private string $referencedColumn = '';

    /**
     * @var string
     */
    private string $referencedTable = '';

    /**
     * ForeignKeyDefinition Constructor
     *
     * @param string $column
     */
    public function __construct(private readonly string $column)
    {
    }

    /**
     * @param string $column
     *
     * @return self
     */
    public function references(string $column): self
    {
        $this->referencedColumn = $column;

        return $this;
    }

    /**
     * @param string $table
     *
     * @return self
     */
    public function on(string $table): self
    {
        $this->referencedTable = $table;

        return $this;
    }

    /**
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * @return string
     */
    public function getReferencedColumn(): string
    {
        return $this->referencedColumn;
    }

    /**
     * @return string
     */
    public function getReferencedTable(): string
    {
        return $this->referencedTable;
    }
}
