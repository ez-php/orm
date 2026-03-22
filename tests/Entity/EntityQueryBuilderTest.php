<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\RepositoryTestCase;

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class ArticleEntity extends Entity
{
    protected static string $table = 'articles';

    protected static array $fillable = ['title', 'published'];
}

/** @extends AbstractRepository<ArticleEntity> */
final class ArticleRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return ArticleEntity::class;
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

#[CoversClass(EntityQueryBuilder::class)]
final class EntityQueryBuilderTest extends RepositoryTestCase
{
    private ArticleRepository $articles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->articles = new ArticleRepository($this->db, $this->hydrator);
    }

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, published INTEGER)');
    }

    private function seedArticles(): void
    {
        $this->exec("INSERT INTO articles (title, published) VALUES ('Alpha', 1)");
        $this->exec("INSERT INTO articles (title, published) VALUES ('Beta', 0)");
        $this->exec("INSERT INTO articles (title, published) VALUES ('Gamma', 1)");
    }

    // ─── get() ───────────────────────────────────────────────────────────────

    public function testGetReturnsAllRows(): void
    {
        $this->seedArticles();

        /** @var list<Entity> $results */
        $results = $this->articles->query()->get();

        self::assertCount(3, $results);
        self::assertContainsOnlyInstancesOf(ArticleEntity::class, $results);
    }

    public function testGetWithWhereClause(): void
    {
        $this->seedArticles();

        $results = $this->articles->query()->where('published', 1)->get();

        self::assertCount(2, $results);
    }

    // ─── first() ─────────────────────────────────────────────────────────────

    public function testFirstReturnsFirstEntity(): void
    {
        $this->seedArticles();

        $article = $this->articles->query()->orderBy('id')->first();

        self::assertNotNull($article);
        self::assertSame('Alpha', $article->getAttribute('title'));
    }

    public function testFirstReturnsNullWhenNoMatch(): void
    {
        self::assertNull($this->articles->query()->where('title', 'Nonexistent')->first());
    }

    // ─── count() ─────────────────────────────────────────────────────────────

    public function testCountReturnsCorrectNumber(): void
    {
        $this->seedArticles();

        self::assertSame(3, $this->articles->query()->count());
        self::assertSame(2, $this->articles->query()->where('published', 1)->count());
    }

    // ─── where/whereIn/whereNull/whereNotNull ─────────────────────────────────

    public function testWhereInFilters(): void
    {
        $this->seedArticles();

        $results = $this->articles->query()->whereIn('title', ['Alpha', 'Gamma'])->get();

        self::assertCount(2, $results);
    }

    public function testWhereNotInFilters(): void
    {
        $this->seedArticles();

        $results = $this->articles->query()->whereNotIn('title', ['Alpha'])->get();

        self::assertCount(2, $results);
    }

    public function testWhereNullFilters(): void
    {
        $this->exec("INSERT INTO articles (title, published) VALUES ('Null Article', NULL)");

        $results = $this->articles->query()->whereNull('published')->get();

        self::assertCount(1, $results);
        self::assertSame('Null Article', $results[0]->getAttribute('title'));
    }

    public function testWhereNotNullFilters(): void
    {
        $this->exec("INSERT INTO articles (title, published) VALUES ('Null Article', NULL)");
        $this->seedArticles();

        $results = $this->articles->query()->whereNotNull('published')->get();

        self::assertCount(3, $results);
    }

    // ─── orderBy / limit / offset ────────────────────────────────────────────

    public function testOrderByAscending(): void
    {
        $this->seedArticles();

        $results = $this->articles->query()->orderBy('title', 'ASC')->get();

        self::assertSame('Alpha', $results[0]->getAttribute('title'));
        self::assertSame('Beta', $results[1]->getAttribute('title'));
        self::assertSame('Gamma', $results[2]->getAttribute('title'));
    }

    public function testOrderByDescending(): void
    {
        $this->seedArticles();

        $results = $this->articles->query()->orderBy('title', 'DESC')->get();

        self::assertSame('Gamma', $results[0]->getAttribute('title'));
    }

    public function testLimitAndOffset(): void
    {
        $this->seedArticles();

        $results = $this->articles->query()->orderBy('id')->limit(2)->offset(1)->get();

        self::assertCount(2, $results);
        self::assertSame('Beta', $results[0]->getAttribute('title'));
    }

    // ─── paginate() ──────────────────────────────────────────────────────────

    public function testPaginateReturnsPaginator(): void
    {
        $this->seedArticles();

        $paginator = $this->articles->query()->paginate(2, 1);

        self::assertInstanceOf(Paginator::class, $paginator);
        self::assertCount(2, $paginator->items());
        self::assertSame(3, $paginator->total());
        self::assertSame(2, $paginator->lastPage());
    }

    public function testPaginateSecondPage(): void
    {
        $this->seedArticles();

        $paginator = $this->articles->query()->orderBy('id')->paginate(2, 2);

        self::assertCount(1, $paginator->items());
    }

    // ─── chunk() ─────────────────────────────────────────────────────────────

    public function testChunkIteratesAllRows(): void
    {
        $this->seedArticles();

        $seen = [];
        $this->articles->query()->orderBy('id')->chunk(2, function (array $batch) use (&$seen): void {
            foreach ($batch as $article) {
                $seen[] = $article->getAttribute('title');
            }
        });

        self::assertCount(3, $seen);
        self::assertSame('Alpha', $seen[0]);
        self::assertSame('Gamma', $seen[2]);
    }

    // ─── Immutability ────────────────────────────────────────────────────────

    public function testBuilderMethodsReturnNewInstance(): void
    {
        $qb1 = $this->articles->query();
        $qb2 = $qb1->where('published', 1);

        self::assertNotSame($qb1, $qb2);
        // Original query is unaffected
        self::assertCount(0, $qb1->get()); // no rows yet
    }
}
