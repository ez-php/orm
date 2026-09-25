<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\CommandRegistryInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Orm\Console\MakeEntityCommand;
use EzPhp\Orm\Console\MakeRepositoryCommand;

/**
 * Class EntityServiceProvider
 *
 * Wires the shared DatabaseInterface into the Entity registry so that
 * AbstractRepository instances can resolve the connection without requiring
 * an explicit constructor argument, and registers the make:entity /
 * make:repository scaffolding commands when running inside the ez-php
 * Application (any container implementing CommandRegistryInterface).
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

        if ($this->app instanceof CommandRegistryInterface) {
            $this->app->registerCommand(MakeEntityCommand::class);
            $this->app->registerCommand(MakeRepositoryCommand::class);
        }
    }
}
