<?php

declare(strict_types=1);

namespace Tests\Schema;

use EzPhp\Application\Application;
use EzPhp\Orm\Schema\Blueprint;
use EzPhp\Orm\Schema\ColumnDefinition;
use EzPhp\Orm\Schema\Schema;
use EzPhp\Orm\Schema\SchemaServiceProvider;
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
#[UsesClass(Blueprint::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(Schema::class)]
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
