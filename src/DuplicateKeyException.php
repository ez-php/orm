<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\EzPhpException;
use PDOException;

/**
 * Thrown when an INSERT violates a primary-key or unique-key constraint.
 *
 * `QueryBuilder::insert()` and `insertBatch()` — and therefore every repository `save()` INSERT —
 * translate the driver's duplicate-key `PDOException` into this exception (kept as `getPrevious()`).
 * Other integrity violations (NOT NULL, foreign key) are not translated and stay `PDOException`.
 *
 * @package EzPhp\Orm
 */
final class DuplicateKeyException extends EzPhpException
{
    /**
     * Wrap the given exception when it is a duplicate-key violation on MySQL/MariaDB (1062),
     * PostgreSQL (SQLSTATE 23505) or SQLite (`UNIQUE`/`PRIMARY KEY` constraint failed).
     *
     * @param PDOException $e
     *
     * @return self|null Null when the error is not a duplicate-key violation.
     */
    public static function fromPdo(PDOException $e): ?self
    {
        $state = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;
        $message = $e->errorInfo[2] ?? $e->getMessage();

        $duplicate = $state === '23505'
            || $driverCode === 1062
            || ($state === '23000' && is_string($message) && preg_match('/\b(UNIQUE|PRIMARY KEY)\b/i', $message) === 1);

        return $duplicate ? new self($e->getMessage(), 0, $e) : null;
    }
}
