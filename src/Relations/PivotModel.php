<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\Model;

/**
 * Class PivotModel
 *
 * Abstract base for custom pivot models used with BelongsToMany::using().
 * Subclasses define their table name, fillable columns, and any extra casts.
 *
 * @package EzPhp\Orm\Relations
 */
abstract class PivotModel extends Model
{
}
