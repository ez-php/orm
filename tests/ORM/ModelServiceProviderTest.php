<?php

declare(strict_types=1);

namespace Tests\ORM;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\ModelServiceProvider;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\PdoDatabase;
use Tests\TestCase;
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
final class ModelServiceProviderTest extends TestCase
{
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
        $db = new PdoDatabase('sqlite::memory:');

        $container = new class ($db) implements ContainerInterface {
            /** @var array<string, callable> */
            private array $bindings = [];

            /** @var array<string, object> */
            private array $instances = [];

            public function __construct(DatabaseInterface $db)
            {
                $this->instances[DatabaseInterface::class] = $db;
            }

            public function bind(string $abstract, string|callable|null $factory = null): void
            {
                if (is_callable($factory)) {
                    $this->bindings[$abstract] = $factory;
                }
            }

            public function instance(string $abstract, object $instance): void
            {
                $this->instances[$abstract] = $instance;
            }

            /**
             * @template T of object
             * @param class-string<T> $abstract
             * @return T
             */
            public function make(string $abstract): mixed
            {
                if (isset($this->instances[$abstract])) {
                    /** @var T */
                    return $this->instances[$abstract];
                }

                if (isset($this->bindings[$abstract])) {
                    /** @var T */
                    return $this->instances[$abstract] = ($this->bindings[$abstract])($this);
                }

                throw new \RuntimeException("No binding registered for {$abstract}.");
            }
        };

        $provider = new ModelServiceProvider($container);
        $provider->boot();

        // After boot(), ModelServiceProvider sets the database on Model.
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
