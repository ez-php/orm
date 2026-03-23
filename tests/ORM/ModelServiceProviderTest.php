<?php

declare(strict_types=1);

namespace Tests\ORM;

use EzPhp\Application\Application;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;
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
#[UsesClass(AbstractRepository::class)]
#[UsesClass(Entity::class)]
#[UsesClass(Hydrator::class)]
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
        Entity::resetDatabase();
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

    /**
     * @return void
     */
    public function test_boot_sets_database_on_entity(): void
    {
        // ModelServiceProvider::boot() must also wire Entity so that AbstractRepository
        // instances created without an explicit DatabaseInterface can resolve the connection.
        $threwNoDatabaseException = false;

        try {
            Entity::database();
        } catch (EzPhpException $e) {
            if (str_contains($e->getMessage(), 'No database resolver set')) {
                $threwNoDatabaseException = true;
            }
        }

        $this->assertFalse($threwNoDatabaseException, 'ModelServiceProvider should set database on Entity during boot.');
    }

    /**
     * @return void
     */
    public function test_abstract_repository_uses_entity_database_when_no_db_injected(): void
    {
        // When AbstractRepository is instantiated without an explicit DatabaseInterface,
        // it must fall back to Entity::database() wired by ModelServiceProvider.
        $repo = new TestBootEntityRepository();

        // The repository was created without throwing — Entity::database() was resolved.
        $this->assertInstanceOf(AbstractRepository::class, $repo);
    }
}

/**
 * @internal Test entity for ModelServiceProviderTest
 */
final class TestBootEntity extends Entity
{
    protected static string $table = 'test_boot_entities';
}

/**
 * @internal Test repository for ModelServiceProviderTest
 *
 * @extends AbstractRepository<TestBootEntity>
 */
final class TestBootEntityRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return TestBootEntity::class;
    }
}

/**
 * @internal Test model for ModelServiceProviderTest
 */
final class TestBootUser extends Model
{
    protected static string $table = 'test_boot_users';
}
