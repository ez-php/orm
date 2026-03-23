<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class ModelServiceProvider
 *
 * @package EzPhp\Orm
 */
final class ModelServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(): void
    {
        $db = $this->app->make(DatabaseInterface::class);
        Model::setDatabase($db);
        Entity::setDatabase($db);
    }
}
