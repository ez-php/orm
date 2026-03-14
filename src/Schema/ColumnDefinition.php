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
}
