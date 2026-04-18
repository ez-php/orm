<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

/**
 * Class Expression
 *
 * Wraps a raw SQL fragment so Blueprint can emit it verbatim in DEFAULT clauses
 * instead of quoting it as a string literal.
 *
 * @package EzPhp\Orm\Schema
 */
final class Expression
{
    /**
     * Expression Constructor
     *
     * @param string $value Raw SQL fragment (e.g. 'CURRENT_TIMESTAMP', '(UUID())')
     */
    public function __construct(private readonly string $value)
    {
    }

    /**
     * @param string $value Raw SQL fragment
     *
     * @return self
     */
    public static function raw(string $value): self
    {
        return new self($value);
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
