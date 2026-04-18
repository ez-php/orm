<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\Schema\SchemaInterface;
use EzPhp\Contracts\ServiceProvider;

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
        $this->app->bind(Schema::class, fn () => new Schema($this->app->make(DatabaseInterface::class)));
        $this->app->bind(SchemaInterface::class, fn () => $this->app->make(Schema::class));
    }
}
