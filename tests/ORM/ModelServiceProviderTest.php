<?php

declare(strict_types=1);

namespace Tests\ORM;

use EzPhp\Application\Application;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Exceptions\EzPhpException;
use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\ModelServiceProvider;
use EzPhp\Orm\QueryBuilder;
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
#[UsesClass(Model::class)]
#[UsesClass(ModelQueryBuilder::class)]
#[UsesClass(QueryBuilder::class)]
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
