<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

use EzPhp\Database\Database;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class SchemaServiceProvider
 *
 * @package EzPhp\Orm\Schema
 */
final class SchemaServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Schema::class, fn () => new Schema($this->app->make(Database::class)));
    }
}
