<?php

declare(strict_types=1);

namespace Tests\Schema;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Orm\Schema\Blueprint;
use EzPhp\Orm\Schema\ColumnDefinition;
use EzPhp\Orm\Schema\Schema;
use EzPhp\Orm\Schema\SchemaServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\PdoDatabase;
use Tests\TestCase;

/**
 * Class SchemaServiceProviderTest
 *
 * @package Tests\Database\Schema
 */
#[CoversClass(SchemaServiceProvider::class)]
#[UsesClass(Blueprint::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(Schema::class)]
final class SchemaServiceProviderTest extends TestCase
{
    /**
     * Build a container stub with DatabaseInterface pre-bound to a SQLite instance.
     */
    private function makeBootedContainer(): ContainerInterface
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

        $provider = new SchemaServiceProvider($container);
        $provider->register();

        return $container;
    }

    /**
     * @return void
     */
    public function test_register_binds_schema_into_container(): void
    {
        $container = $this->makeBootedContainer();

        $this->assertInstanceOf(Schema::class, $container->make(Schema::class));
    }

    /**
     * @return void
     */
    public function test_schema_resolves_as_singleton(): void
    {
        $container = $this->makeBootedContainer();

        $s1 = $container->make(Schema::class);
        $s2 = $container->make(Schema::class);

        $this->assertSame($s1, $s2);
    }
}
