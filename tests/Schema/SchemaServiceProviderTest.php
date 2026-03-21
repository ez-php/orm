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
use Tests\ApplicationTestCase;

/**
 * Class SchemaServiceProviderTest
 *
 * @package Tests\Database\Schema
 */
#[CoversClass(SchemaServiceProvider::class)]
#[UsesClass(Blueprint::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(Schema::class)]
final class SchemaServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(SchemaServiceProvider::class);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_register_binds_schema_into_container(): void
    {
        $this->assertInstanceOf(Schema::class, $this->app()->make(Schema::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_schema_resolves_as_singleton(): void
    {
        $s1 = $this->app()->make(Schema::class);
        $s2 = $this->app()->make(Schema::class);

        $this->assertSame($s1, $s2);
    }
}
