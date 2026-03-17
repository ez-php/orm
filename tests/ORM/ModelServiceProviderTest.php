<?php

declare(strict_types=1);

namespace Tests\ORM;

use EzPhp\Application\Application;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\ModelServiceProvider;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\ApplicationTestCase;
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
final class ModelServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(ModelServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Model::resetDatabase();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_boot_sets_database_on_model(): void
    {
        // ModelServiceProvider::boot() is called during bootstrap and sets the
        // database on Model. Verify no EzPhpException ("No database set") is thrown.
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
