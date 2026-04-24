<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\AppendOnlyRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;
use EzPhp\Orm\QueryBuilder;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\RepositoryTestCase;

/**
 * Entity fixture for append-only tests.
 */
final class AuditLogEntity extends Entity
{
    protected static string $table = 'audit_logs';

    protected static array $fillable = ['message', 'level'];
}

/**
 * Concrete append-only repository fixture.
 *
 * @extends AppendOnlyRepository<AuditLogEntity>
 */
final class AuditLogRepository extends AppendOnlyRepository
{
    protected function entityClass(): string
    {
        return AuditLogEntity::class;
    }
}

/**
 * Class AppendOnlyRepositoryTest
 *
 * @package Tests\Entity
 */
#[CoversClass(AppendOnlyRepository::class)]
#[UsesClass(Entity::class)]
#[UsesClass(Hydrator::class)]
#[UsesClass(QueryBuilder::class)]
final class AppendOnlyRepositoryTest extends RepositoryTestCase
{
    private AuditLogRepository $repo;

    /**
     * @return void
     */
    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, message TEXT NOT NULL, level TEXT NOT NULL)');
        Entity::setDatabase($this->db);
        $this->repo = new AuditLogRepository($this->db, $this->hydrator);
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
    public function test_create_inserts_and_returns_entity_with_id(): void
    {
        $entity = $this->repo->create(['message' => 'User logged in', 'level' => 'info']);

        $this->assertInstanceOf(AuditLogEntity::class, $entity);
        $this->assertNotNull($entity->getAttribute('id'));
        $this->assertSame('User logged in', $entity->getAttribute('message'));
        $this->assertSame('info', $entity->getAttribute('level'));
    }

    /**
     * @return void
     */
    public function test_create_persists_to_database(): void
    {
        $this->repo->create(['message' => 'First', 'level' => 'info']);
        $this->repo->create(['message' => 'Second', 'level' => 'warn']);

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
    }

    /**
     * @return void
     */
    public function test_save_throws_logic_exception(): void
    {
        $entity = new AuditLogEntity(['message' => 'x', 'level' => 'info']);

        $this->expectException(LogicException::class);
        $this->repo->save($entity);
    }

    /**
     * @return void
     */
    public function test_delete_throws_logic_exception(): void
    {
        $entity = $this->repo->create(['message' => 'x', 'level' => 'info']);

        $this->expectException(LogicException::class);
        $this->repo->delete($entity);
    }

    /**
     * @return void
     */
    public function test_save_exception_message_contains_class_name(): void
    {
        $entity = new AuditLogEntity(['message' => 'x', 'level' => 'info']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/AuditLogRepository/');
        $this->repo->save($entity);
    }

    /**
     * @return void
     */
    public function test_find_works_after_create(): void
    {
        $created = $this->repo->create(['message' => 'test', 'level' => 'debug']);
        $id = $created->getAttribute('id');
        assert(is_int($id) || is_string($id));

        $found = $this->repo->find((int) $id);

        $this->assertNotNull($found);
        $this->assertSame('test', $found->getAttribute('message'));
    }
}
