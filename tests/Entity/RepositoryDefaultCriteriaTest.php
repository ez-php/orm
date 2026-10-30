<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Hydrator;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\PdoDatabase;
use Tests\RepositoryTestCase;

/**
 * A tenant-owned row.
 */
final class CriteriaProjectEntity extends Entity
{
    protected static string $table = 'criteria_projects';
}

/**
 * Scopes every read to one tenant, as an application repository would.
 *
 * @extends AbstractRepository<CriteriaProjectEntity>
 */
final class CriteriaProjectRepository extends AbstractRepository
{
    public function __construct(PdoDatabase $db, Hydrator $hydrator, private readonly string $tenant)
    {
        parent::__construct($db, $hydrator);
    }

    protected function entityClass(): string
    {
        return CriteriaProjectEntity::class;
    }

    protected function defaultCriteria(QueryBuilder $query): QueryBuilder
    {
        return $query->where('tenant_id', $this->tenant);
    }
}

/**
 * AbstractRepository::defaultCriteria() applies to every read path and can be bypassed explicitly.
 *
 * @package Tests\Entity
 */
#[CoversClass(AbstractRepository::class)]
#[UsesClass(EntityQueryBuilder::class)]
#[UsesClass(QueryBuilder::class)]
final class RepositoryDefaultCriteriaTest extends RepositoryTestCase
{
    private CriteriaProjectRepository $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = new CriteriaProjectRepository($this->db, $this->hydrator, 'acme');
    }

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE criteria_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, name TEXT NOT NULL)');
        $this->exec("INSERT INTO criteria_projects (tenant_id, name) VALUES ('acme', 'Rocket'), ('globex', 'Dome'), ('acme', 'Anvil')");
    }

    public function test_every_read_path_is_scoped(): void
    {
        $names = static fn (array $entities): array => array_map(static fn (Entity $e): mixed => $e->getAttribute('name'), $entities);

        self::assertSame(['Rocket', 'Anvil'], $names($this->acme->findAll()));
        self::assertSame(['Rocket', 'Anvil'], $names($this->acme->query()->get()));
        self::assertSame(['Rocket', 'Anvil'], $names($this->acme->findWhereIn('id', [1, 2, 3])));
        self::assertSame([], $this->acme->findBy('name', 'Dome'));
        self::assertNull($this->acme->findOneBy('name', 'Dome'));
        self::assertNull($this->acme->find(2));
        self::assertNull($this->acme->findForUpdate(2));
        self::assertNotNull($this->acme->findForUpdate(1));
        self::assertSame(['acme' => 2], $this->acme->countBy('tenant_id', ['acme', 'globex']));
    }

    public function test_without_default_criteria_reads_everything(): void
    {
        self::assertCount(3, $this->acme->withoutDefaultCriteria()->get());
    }

    public function test_writes_on_loaded_entities_are_unaffected(): void
    {
        $project = $this->acme->find(1);
        assert($project instanceof CriteriaProjectEntity);

        $project->setAttribute('name', 'Rocket 2');
        $this->acme->save($project);

        self::assertSame('Rocket 2', $this->acme->find(1)?->getAttribute('name'));
    }
}
