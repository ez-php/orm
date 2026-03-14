<?php

declare(strict_types=1);

namespace Tests\Schema;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\Console;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Migration\Migrator;
use EzPhp\Orm\Schema\Blueprint;
use EzPhp\Orm\Schema\ColumnDefinition;
use EzPhp\Orm\Schema\Schema;
use EzPhp\Orm\Schema\SchemaServiceProvider;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\DatabaseTestCase;

/**
 * Class SchemaServiceProviderTest
 *
 * @package Tests\Database\Schema
 */
#[CoversClass(SchemaServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(Blueprint::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(Schema::class)]

#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(MiddlewareHandler::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(Console::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]

final class SchemaServiceProviderTest extends DatabaseTestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_register_binds_schema_into_container(): void
    {
        $app = new Application();
        $app->register(SchemaServiceProvider::class);
        $app->bootstrap();

        $schema = $app->make(Schema::class);

        $this->assertInstanceOf(Schema::class, $schema);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_schema_resolves_as_singleton(): void
    {
        $app = new Application();
        $app->register(SchemaServiceProvider::class);
        $app->bootstrap();

        $s1 = $app->make(Schema::class);
        $s2 = $app->make(Schema::class);

        $this->assertSame($s1, $s2);
    }
}
