<?php

declare(strict_types=1);

namespace Tests\Entity;

use BadMethodCallException;
use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Paginator;
use EzPhp\Orm\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\RepositoryTestCase;

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class ArticleEntity extends Entity
{
    protected static string $table = 'articles';

    protected static array $fillable = ['title', 'published'];
}

/**
 * @extends AbstractRepository<ArticleEntity>
 * @method EntityQueryBuilder<ArticleEntity> published()
 * @method EntityQueryBuilder<ArticleEntity> titled(string $title)
 */
final class ArticleRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return ArticleEntity::class;
    }

    /**
     * Scope: only published articles.
     */
    public function scopePublished(QueryBuilder $qb): QueryBuilder
    {
        return $qb->where('published', 1);
    }

    /**
     * Scope: articles with title matching a pattern (parameterised scope).
     */
    public function scopeTitled(QueryBuilder $qb, string $title): QueryBuilder
    {
        return $qb->where('title', $title);
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

#[CoversClass(EntityQueryBuilder::class)]
#[UsesClass(QueryBuilder::class)]
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

    // ─── join() ──────────────────────────────────────────────────────────────

    public function testJoinFiltersToMatchingRows(): void
    {
        $this->exec('CREATE TABLE labels (id INTEGER PRIMARY KEY, article_id INTEGER, name TEXT)');
        $this->exec("INSERT INTO articles (title, published) VALUES ('Alpha', 1)");
        $this->exec("INSERT INTO articles (title, published) VALUES ('Beta', 1)");
        $this->exec('INSERT INTO labels (article_id, name) VALUES (1, \'Urgent\')');
        // Beta has no label

        $results = $this->articles->query()
            ->join('labels', 'labels.article_id', '=', 'articles.id')
            ->get();

        self::assertCount(1, $results);
        self::assertSame('Alpha', $results[0]->getAttribute('title'));
    }

    // ─── __call / scopes ─────────────────────────────────────────────────────
    //
    // PHPStan does not resolve magic methods dispatched by __call at the call
    // site. Scope tests therefore invoke __call() directly — the mechanism is
    // identical to the magic `->scopeName()` syntax at runtime.

    public function testScopeFiltersResults(): void
    {
        $this->seedArticles();

        /** @var list<ArticleEntity> $results */
        $results = $this->articles->query()->__call('published', [])->get();

        self::assertCount(2, $results);

        foreach ($results as $entity) {
            self::assertSame(1, $entity->getAttribute('published'));
        }
    }

    public function testScopeCanBeChainedWithWhereClause(): void
    {
        $this->seedArticles();

        /** @var list<ArticleEntity> $results */
        $results = $this->articles->query()->__call('published', [])->where('title', 'Alpha')->get();

        self::assertCount(1, $results);
        self::assertSame('Alpha', $results[0]->getAttribute('title'));
    }

    public function testScopeReturnsClonesNotSameInstance(): void
    {
        $qb1 = $this->articles->query();
        $qb2 = $qb1->__call('published', []);

        self::assertNotSame($qb1, $qb2);
    }

    public function testParameterisedScope(): void
    {
        $this->seedArticles();

        /** @var list<ArticleEntity> $results */
        $results = $this->articles->query()->__call('titled', ['Alpha'])->get();

        self::assertCount(1, $results);
        self::assertSame('Alpha', $results[0]->getAttribute('title'));
    }

    public function testScopeThrowsBadMethodCallExceptionForUnknownScope(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/Scope \[nonexistent\] not found/');

        $this->articles->query()->__call('nonexistent', []);
    }
}
