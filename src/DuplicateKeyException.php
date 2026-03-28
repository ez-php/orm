<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\EzPhpException;

/**
 * Thrown when an INSERT violates a primary-key or unique-key constraint (SQLSTATE 23xxx).
 *
 * @package EzPhp\Orm
 */
final class DuplicateKeyException extends EzPhpException
{
}
