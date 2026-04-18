<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

use InvalidArgumentException;

/**
 * Class ForeignKeyDefinition
 *
 * @package EzPhp\Orm\Schema
 */
final class ForeignKeyDefinition
{
    private const array ALLOWED_ACTIONS = ['cascade', 'restrict', 'set null', 'no action'];

    private string $referencedColumn = '';

    /**
     * @var string
     */
    private string $referencedTable = '';

    private ?string $onDelete = null;

    private ?string $onUpdate = null;

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
     * Infer references('id')->on(<table>) from the column name.
     *
     * If $table is null, the table is derived by stripping the trailing `_id`
     * from the column name and appending `s` (e.g. `user_id` → `users`).
     *
     * @param string|null $table Explicit table override; inferred when null.
     *
     * @return self
     */
    public function constrained(?string $table = null): self
    {
        if ($table === null) {
            $base = preg_replace('/_id$/', '', $this->column) ?? $this->column;
            $table = $base . 's';
        }

        return $this->references('id')->on($table);
    }

    /**
     * @param string $action cascade|restrict|set null|no action
     *
     * @return self
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = $this->validateAction($action);

        return $this;
    }

    /**
     * @param string $action cascade|restrict|set null|no action
     *
     * @return self
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = $this->validateAction($action);

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

    /**
     * @return string|null
     */
    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    /**
     * @return string|null
     */
    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    /**
     * @param string $action
     *
     * @return string
     */
    private function validateAction(string $action): string
    {
        $lower = strtolower($action);

        if (!in_array($lower, self::ALLOWED_ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Invalid FK action '$action'. Allowed: " . implode(', ', self::ALLOWED_ACTIONS) . '.'
            );
        }

        return strtoupper($lower);
    }
}
