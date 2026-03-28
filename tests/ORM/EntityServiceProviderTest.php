<?php

declare(strict_types=1);

namespace Tests\ORM;

use EzPhp\Application\Application;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityServiceProvider;
use EzPhp\Orm\Hydrator;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\ApplicationTestCase;

/**
 * Class EntityServiceProviderTest
 *
 * @package Tests\ORM
 */
#[CoversClass(EntityServiceProvider::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(Entity::class)]
#[UsesClass(Hydrator::class)]
#[UsesClass(QueryBuilder::class)]
final class EntityServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(EntityServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Entity::resetDatabase();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_boot_sets_database_on_entity(): void
    {
        // EntityServiceProvider::boot() must wire Entity so that AbstractRepository
        // instances created without an explicit DatabaseInterface can resolve the connection.
        $threwNoDatabaseException = false;

        try {
            Entity::database();
        } catch (EzPhpException $e) {
            if (str_contains($e->getMessage(), 'No database resolver set')) {
                $threwNoDatabaseException = true;
            }
        }

        $this->assertFalse($threwNoDatabaseException, 'EntityServiceProvider should set database on Entity during boot.');
    }

    /**
     * @return void
     */
    public function test_abstract_repository_uses_entity_database_when_no_db_injected(): void
    {
        // When AbstractRepository is instantiated without an explicit DatabaseInterface,
        // it must fall back to Entity::database() wired by EntityServiceProvider.
        $repo = new EntityServiceProviderTestEntityRepository();

        $this->assertInstanceOf(AbstractRepository::class, $repo);
    }
}

/**
 * @internal
 */
final class EntityServiceProviderTestEntity extends Entity
{
    protected static string $table = 'esp_test_entities';
}

/**
 * @internal
 *
 * @extends AbstractRepository<EntityServiceProviderTestEntity>
 */
final class EntityServiceProviderTestEntityRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return EntityServiceProviderTestEntity::class;
    }
}
