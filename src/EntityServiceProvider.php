<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class EntityServiceProvider
 *
 * Wires the shared DatabaseInterface into the Entity registry so that
 * AbstractRepository instances can resolve the connection without requiring
 * an explicit constructor argument.
 *
 * Register in provider/modules.php:
 *   EzPhp\Orm\EntityServiceProvider::class,
 *
 * @package EzPhp\Orm
 */
final class EntityServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(): void
    {
        $db = $this->app->make(DatabaseInterface::class);
        Entity::setDatabase($db);
    }
}
