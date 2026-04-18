<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

/**
 * Class ColumnDefinition
 *
 * @package EzPhp\Orm\Schema
 */
final class ColumnDefinition
{
    private bool $nullable = false;

    private bool $unique = false;

    private mixed $defaultValue = null;

    private bool $hasDefault = false;

    private ?string $checkConstraint = null;

    private ?string $afterColumn = null;

    private bool $isFirst = false;

    private bool $isChanged = false;

    private bool $onUpdateCurrent = false;

    /**
     * ColumnDefinition Constructor
     *
     * @param string $name
     * @param string $sqlType
     * @param bool   $primaryKey
     */
    public function __construct(
        public readonly string $name,
        public readonly string $sqlType,
        public readonly bool $primaryKey = false,
    ) {
    }

    /**
     * @return self
     */
    public function nullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * @return self
     */
    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return self
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;

        return $this;
    }

    /**
     * Add a CHECK constraint to this column.
     *
     * @param string $constraint
     *
     * @return self
     */
    public function withCheck(string $constraint): self
    {
        $this->checkConstraint = $constraint;

        return $this;
    }

    /**
     * Place this column AFTER another column (MySQL ALTER only).
     *
     * @param string $column
     *
     * @return self
     */
    public function after(string $column): self
    {
        $this->afterColumn = $column;

        return $this;
    }

    /**
     * Place this column FIRST (MySQL ALTER only).
     *
     * @return self
     */
    public function first(): self
    {
        $this->isFirst = true;

        return $this;
    }

    /**
     * Mark this column as being modified (MODIFY COLUMN in MySQL ALTER).
     *
     * @return self
     */
    public function change(): self
    {
        $this->isChanged = true;

        return $this;
    }

    /**
     * Set DEFAULT CURRENT_TIMESTAMP — shorthand for ->default(Expression::raw('CURRENT_TIMESTAMP')).
     *
     * @return self
     */
    public function useCurrent(): self
    {
        return $this->default(Expression::raw('CURRENT_TIMESTAMP'));
    }

    /**
     * Append ON UPDATE CURRENT_TIMESTAMP to the column definition.
     *
     * @return self
     */
    public function useCurrentOnUpdate(): self
    {
        $this->onUpdateCurrent = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return bool
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * @return bool
     */
    public function isPrimaryKey(): bool
    {
        return $this->primaryKey;
    }

    /**
     * @return bool
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasDefault;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * @return string|null
     */
    public function getCheck(): ?string
    {
        return $this->checkConstraint;
    }

    /**
     * @return string|null
     */
    public function getAfterColumn(): ?string
    {
        return $this->afterColumn;
    }

    /**
     * @return bool
     */
    public function isFirstColumn(): bool
    {
        return $this->isFirst;
    }

    /**
     * @return bool
     */
    public function isChanged(): bool
    {
        return $this->isChanged;
    }

    /**
     * @return bool
     */
    public function hasOnUpdateCurrent(): bool
    {
        return $this->onUpdateCurrent;
    }
}
