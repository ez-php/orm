<?php

declare(strict_types=1);

namespace Tests\ORM;

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
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Exceptions\EzPhpException;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Migration\Migrator;
use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\ModelServiceProvider;
use EzPhp\Orm\QueryBuilder;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\DatabaseTestCase;
use Throwable;

/**
 * Class ModelServiceProviderTest
 *
 * @package Tests\Database\ORM
 */
#[CoversClass(ModelServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(Model::class)]
#[UsesClass(ModelQueryBuilder::class)]

#[UsesClass(QueryBuilder::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(Console::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]

final class ModelServiceProviderTest extends DatabaseTestCase
{
    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_boot_sets_database_on_model(): void
    {
        $app = new Application();
        $app->register(ModelServiceProvider::class);
        $app->bootstrap();

        // After bootstrap, ModelServiceProvider::boot() sets the database on Model.
        // Verify that no EzPhpException ("No database set") is thrown when using Model.
        $threwNoDatabaseException = false;

        try {
            TestBootUser::all();
        } catch (EzPhpException $e) {
            if (str_contains($e->getMessage(), 'No database resolver set')) {
                $threwNoDatabaseException = true;
            }
        } catch (Throwable) {
            // PDO or table errors are expected — the database is set, just no table exists
        }

        $this->assertFalse($threwNoDatabaseException, 'ModelServiceProvider should set database on Model during boot.');
    }
}

/**
 * @internal Test model for ModelServiceProviderTest
 */
final class TestBootUser extends Model
{
    protected static string $table = 'test_boot_users';
}
