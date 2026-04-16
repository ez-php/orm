<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityQueryBuilder;
use EzPhp\Orm\Relations\EntityBelongsTo;
use EzPhp\Orm\Relations\EntityBelongsToMany;
use EzPhp\Orm\Relations\EntityHasMany;
use EzPhp\Orm\Relations\EntityHasOne;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\RepositoryTestCase;

// ─── Entity fixtures ─────────────────────────────────────────────────────────

final class UserEntity extends Entity
{
    protected static string $table = 'users';

    protected static array $fillable = ['name', 'email'];
}

final class PostEntity extends Entity
{
    protected static string $table = 'posts';

    protected static array $fillable = ['user_id', 'title'];
}

final class ProfileEntity extends Entity
{
    protected static string $table = 'profiles';

    protected static array $fillable = ['user_id', 'bio'];
}

final class TagEntity extends Entity
{
    protected static string $table = 'tags';

    protected static array $fillable = ['name'];
}

final class TimestampedEntity extends Entity
{
    protected static string $table = 'timestamped';

    protected static array $fillable = ['name'];

    protected static bool $timestamps = true;
}

final class SoftDeleteEntity extends Entity
{
    protected static string $table = 'soft_delete';

    protected static array $fillable = ['name'];

    protected static bool $softDeletes = true;
}

final class CastEntity extends Entity
{
    protected static string $table = 'cast_records';

    protected static array $fillable = ['name', 'score', 'active', 'tags'];

    protected static array $casts = ['score' => 'int', 'active' => 'bool', 'tags' => 'array'];
}

final class CompositeEntity extends Entity
{
    protected static string $table = 'composites';

    protected static string|array $primaryKey = ['user_id', 'role_id'];

    protected static array $fillable = ['user_id', 'role_id', 'label'];
}

// ─── Repository fixtures ─────────────────────────────────────────────────────

/**
 * @extends AbstractRepository<UserEntity>
 */
final class UserRepository extends AbstractRepository
{
    private ?PostRepository $postRepo = null;

    private ?ProfileRepository $profileRepo = null;

    private ?TagRepository $tagRepo = null;

    public function setPostRepo(PostRepository $repo): void
    {
        $this->postRepo = $repo;
    }

    public function setProfileRepo(ProfileRepository $repo): void
    {
        $this->profileRepo = $repo;
    }

    public function setTagRepo(TagRepository $repo): void
    {
        $this->tagRepo = $repo;
    }

    protected function entityClass(): string
    {
        return UserEntity::class;
    }

    // Relation methods accept Entity (base type) because EntityQueryBuilder calls them
    // with a generic Entity instance; callers that have concrete types may also pass UserEntity.

    /** @return EntityHasMany<PostEntity> */
    public function posts(Entity $user): EntityHasMany
    {
        assert($this->postRepo !== null);

        return $this->hasMany($this->postRepo, 'user_id', 'id', $user->getAttribute('id'));
    }

    /** @return EntityHasOne<ProfileEntity> */
    public function profile(Entity $user): EntityHasOne
    {
        assert($this->profileRepo !== null);

        return $this->hasOne($this->profileRepo, 'user_id', 'id', $user->getAttribute('id'));
    }

    /** @return EntityBelongsToMany<TagEntity> */
    public function tags(Entity $user): EntityBelongsToMany
    {
        assert($this->tagRepo !== null);

        return $this->belongsToMany(
            $this->tagRepo,
            'tags',
            'user_tags',
            'user_id',
            'tag_id',
            'id',
            'id',
            $user->getAttribute('id'),
        );
    }
}

/**
 * @extends AbstractRepository<PostEntity>
 */
final class PostRepository extends AbstractRepository
{
    private ?UserRepository $userRepo = null;

    public function setUserRepo(UserRepository $repo): void
    {
        $this->userRepo = $repo;
    }

    protected function entityClass(): string
    {
        return PostEntity::class;
    }

    /** @return EntityBelongsTo<UserEntity> */
    public function user(Entity $post): EntityBelongsTo
    {
        assert($this->userRepo !== null);

        return $this->belongsTo($this->userRepo, 'user_id', 'id', $post->getAttribute('user_id'));
    }
}

/**
 * @extends AbstractRepository<ProfileEntity>
 */
final class ProfileRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return ProfileEntity::class;
    }
}

/**
 * @extends AbstractRepository<TagEntity>
 */
final class TagRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return TagEntity::class;
    }
}

/**
 * @extends AbstractRepository<TimestampedEntity>
 */
final class TimestampedRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return TimestampedEntity::class;
    }
}

/**
 * @extends AbstractRepository<SoftDeleteEntity>
 */
final class SoftDeleteRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return SoftDeleteEntity::class;
    }
}

/**
 * @extends AbstractRepository<CastEntity>
 */
final class CastRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return CastEntity::class;
    }
}

/**
 * @extends AbstractRepository<CompositeEntity>
 */
final class CompositeRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return CompositeEntity::class;
    }
}

final class CounterEntity extends Entity
{
    protected static string $table = 'counters';

    protected static array $fillable = ['count'];

    protected static array $casts = ['count' => 'int'];
}

/**
 * @extends AbstractRepository<CounterEntity>
 */
final class CounterRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return CounterEntity::class;
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

#[CoversClass(AbstractRepository::class)]
#[CoversClass(EntityQueryBuilder::class)]
#[CoversClass(EntityHasMany::class)]
#[CoversClass(EntityHasOne::class)]
#[CoversClass(EntityBelongsTo::class)]
#[CoversClass(EntityBelongsToMany::class)]
final class RepositoryTest extends RepositoryTestCase
{
    private UserRepository $users;

    private PostRepository $posts;

    private ProfileRepository $profiles;

    private TagRepository $tags;

    private TimestampedRepository $timestamped;

    private SoftDeleteRepository $softDelete;

    private CastRepository $casts;

    private CompositeRepository $composite;

    private CounterRepository $counters;

    protected function setUp(): void
    {
        parent::setUp();

        $h = $this->hydrator;

        $this->users = new UserRepository($this->db, $h);
        $this->posts = new PostRepository($this->db, $h);
        $this->profiles = new ProfileRepository($this->db, $h);
        $this->tags = new TagRepository($this->db, $h);
        $this->timestamped = new TimestampedRepository($this->db, $h);
        $this->softDelete = new SoftDeleteRepository($this->db, $h);
        $this->casts = new CastRepository($this->db, $h);
        $this->composite = new CompositeRepository($this->db, $h);
        $this->counters = new CounterRepository($this->db, $h);

        // Wire repositories for relation tests
        $this->users->setPostRepo($this->posts);
        $this->users->setProfileRepo($this->profiles);
        $this->users->setTagRepo($this->tags);
        $this->posts->setUserRepo($this->users);
    }

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)');
        $this->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');
        $this->exec('CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, bio TEXT)');
        $this->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->exec('CREATE TABLE user_tags (user_id INTEGER, tag_id INTEGER)');
        $this->exec('CREATE TABLE timestamped (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE soft_delete (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, deleted_at TEXT)');
        $this->exec('CREATE TABLE cast_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, score TEXT, active INTEGER, tags TEXT)');
        $this->exec('CREATE TABLE composites (user_id INTEGER, role_id INTEGER, label TEXT, PRIMARY KEY (user_id, role_id))');
        $this->exec('CREATE TABLE counters (id INTEGER PRIMARY KEY AUTOINCREMENT, count INTEGER NOT NULL DEFAULT 0)');
    }

    // ─── find() ──────────────────────────────────────────────────────────────

    public function testFindReturnsEntityWhenFound(): void
    {
        $this->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");

        $user = $this->users->find(1);

        self::assertInstanceOf(UserEntity::class, $user);
        self::assertSame('Alice', $user->getAttribute('name'));
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->users->find(999));
    }

    // ─── save() — INSERT ─────────────────────────────────────────────────────

    public function testSaveInsertsNewEntity(): void
    {
        $user = new UserEntity(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->users->save($user);

        self::assertNotNull($user->getAttribute('id'));
        self::assertSame(1, $this->intAttr($user));
    }

    public function testSaveInsertedEntityIsSubsequentlyFound(): void
    {
        $user = new UserEntity(['name' => 'Bob']);
        $this->users->save($user);

        $found = $this->users->find($this->intAttr($user));

        self::assertNotNull($found);
        self::assertSame('Bob', $found->getAttribute('name'));
    }

    // ─── save() — UPDATE ─────────────────────────────────────────────────────

    public function testSaveUpdatesOnlyDirtyColumns(): void
    {
        $this->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $user = $this->users->find(1);

        self::assertNotNull($user);
        $user->setAttribute('name', 'Alicia');
        $this->users->save($user);

        $reloaded = $this->users->find(1);
        self::assertNotNull($reloaded);
        self::assertSame('Alicia', $reloaded->getAttribute('name'));
        self::assertSame('alice@example.com', $reloaded->getAttribute('email'));
    }

    public function testSaveIsNoOpWhenNothingIsDirty(): void
    {
        $this->exec("INSERT INTO users (name, email) VALUES ('Alice', 'a@b.com')");
        $user = $this->users->find(1);

        self::assertNotNull($user);
        $this->users->save($user);

        $reloaded = $this->users->find(1);
        self::assertNotNull($reloaded);
        self::assertSame('Alice', $reloaded->getAttribute('name'));
    }

    // ─── delete() ────────────────────────────────────────────────────────────

    public function testDeleteRemovesEntity(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $user = $this->users->find(1);

        self::assertNotNull($user);
        $this->users->delete($user);

        self::assertNull($this->users->find(1));
    }

    // ─── findAll() / findBy() / findOneBy() ──────────────────────────────────

    public function testFindAllReturnsAll(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");

        $all = $this->users->findAll();

        self::assertCount(2, $all);
    }

    public function testFindAllWithLimitReturnsBoundedSet(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->exec("INSERT INTO users (name) VALUES ('User$i')");
        }

        $all = $this->users->findAll(3);

        self::assertCount(3, $all);
    }

    public function testFindAllWithLimitAndOffsetSkipsRows(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO users (name) VALUES ('Carol')");

        // limit=2, offset=1 → skip Alice, return Bob+Carol
        $all = $this->users->findAll(2, 1);

        self::assertCount(2, $all);
        self::assertSame('Bob', $all[0]->getAttribute('name'));
        self::assertSame('Carol', $all[1]->getAttribute('name'));
    }

    public function testFindAllWithZeroLimitReturnsAll(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");

        // limit=0 means no limit
        $all = $this->users->findAll(0);

        self::assertCount(2, $all);
    }

    // ─── findAllWhere() ──────────────────────────────────────────────────────

    public function testFindAllWhereFiltersBySingleCriteria(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");

        $alices = $this->users->findAllWhere(['name' => 'Alice']);

        self::assertCount(2, $alices);

        foreach ($alices as $user) {
            self::assertSame('Alice', $user->getAttribute('name'));
        }
    }

    public function testFindAllWhereFiltersMultipleCriteria(): void
    {
        $this->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@a.com')");
        $this->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@b.com')");
        $this->exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@a.com')");

        $results = $this->users->findAllWhere(['name' => 'Alice', 'email' => 'alice@a.com']);

        self::assertCount(1, $results);
        self::assertSame('alice@a.com', $results[0]->getAttribute('email'));
    }

    public function testFindAllWhereReturnsEmptyWhenNoMatch(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");

        self::assertSame([], $this->users->findAllWhere(['name' => 'Nonexistent']));
    }

    public function testFindAllWhereWithLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        }

        $results = $this->users->findAllWhere(['name' => 'Alice'], 3);

        self::assertCount(3, $results);
    }

    public function testFindAllWhereWithLimitAndOffset(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");

        // limit=2, offset=1 → skip first Alice
        $results = $this->users->findAllWhere(['name' => 'Alice'], 2, 1);

        self::assertCount(2, $results);
        // The two returned entities should be rows 2 and 3
        self::assertSame(2, $this->intAttr($results[0]));
        self::assertSame(3, $this->intAttr($results[1]));
    }

    public function testFindAllWhereWithEmptyCriteriaReturnsAll(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");

        $all = $this->users->findAllWhere([]);

        self::assertCount(2, $all);
    }

    public function testFindByReturnsMatchingEntities(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");

        $alices = $this->users->findBy('name', 'Alice');

        self::assertCount(2, $alices);
    }

    public function testFindOneByReturnsFirstMatch(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");

        $user = $this->users->findOneBy('name', 'Alice');

        self::assertNotNull($user);
        self::assertSame('Alice', $user->getAttribute('name'));
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->users->findOneBy('name', 'Nobody'));
    }

    // ─── Dirty tracking ──────────────────────────────────────────────────────

    public function testNewEntityIsInsertedNotUpdated(): void
    {
        $user = new UserEntity(['name' => 'Charlie']);
        $this->users->save($user);

        $id = $this->intAttr($user);
        self::assertGreaterThan(0, $id);

        $found = $this->users->find($id);
        self::assertNotNull($found);
        self::assertSame('Charlie', $found->getAttribute('name'));
    }

    public function testEntityLoadedViaFindIsTrackedForDirty(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Dan')");
        $user = $this->users->find(1);
        self::assertNotNull($user);

        $user->setAttribute('name', 'Daniel');
        $this->users->save($user);

        $reloaded = $this->users->find(1);
        self::assertNotNull($reloaded);
        self::assertSame('Daniel', $reloaded->getAttribute('name'));
    }

    // ─── Timestamps ──────────────────────────────────────────────────────────

    public function testTimestampsAreSetOnInsert(): void
    {
        $entity = new TimestampedEntity(['name' => 'Item']);
        $this->timestamped->save($entity);

        self::assertNotNull($entity->getAttribute('created_at'));
        self::assertNotNull($entity->getAttribute('updated_at'));
    }

    public function testUpdatedAtIsChangedOnUpdate(): void
    {
        $entity = new TimestampedEntity(['name' => 'Item']);
        $this->timestamped->save($entity);

        $originalUpdatedAt = $entity->getAttribute('updated_at');

        sleep(1);

        $found = $this->timestamped->find($this->intAttr($entity));
        self::assertNotNull($found);
        $found->setAttribute('name', 'Updated Item');
        $this->timestamped->save($found);

        self::assertNotSame($originalUpdatedAt, $found->getAttribute('updated_at'));
    }

    // ─── Soft deletes ────────────────────────────────────────────────────────

    public function testSoftDeleteSetsDeletedAt(): void
    {
        $entity = new SoftDeleteEntity(['name' => 'Item']);
        $this->softDelete->save($entity);

        $id = $this->intAttr($entity);
        $found = $this->softDelete->find($id);
        self::assertNotNull($found);

        $this->softDelete->delete($found);

        self::assertNull($this->softDelete->find($id));

        $rows = $this->db->query("SELECT * FROM soft_delete WHERE id = $id");
        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]['deleted_at']);
    }

    // ─── Casts round-trip ────────────────────────────────────────────────────

    public function testCastRoundTripOnSaveAndFind(): void
    {
        $entity = new CastEntity(['name' => 'r', 'score' => 100, 'active' => true, 'tags' => ['a', 'b']]);
        $this->casts->save($entity);

        $id = $this->intAttr($entity);
        $found = $this->casts->find($id);
        self::assertNotNull($found);

        self::assertSame(100, $found->getAttribute('score'));
        self::assertTrue($found->getAttribute('active'));
        self::assertSame(['a', 'b'], $found->getAttribute('tags'));
    }

    // ─── Composite primary key ───────────────────────────────────────────────

    public function testInsertCompositeEntity(): void
    {
        $entity = new CompositeEntity(['user_id' => 1, 'role_id' => 2, 'label' => 'Admin']);
        $this->composite->save($entity);

        $rows = $this->db->query('SELECT * FROM composites WHERE user_id = 1 AND role_id = 2');
        self::assertCount(1, $rows);
        self::assertSame('Admin', $rows[0]['label']);
    }

    public function testUpdateCompositeEntity(): void
    {
        $this->exec("INSERT INTO composites (user_id, role_id, label) VALUES (1, 2, 'Old')");

        $row = $this->db->query('SELECT * FROM composites WHERE user_id = 1 AND role_id = 2');
        $entity = $this->composite->hydrateTracked($row[0]);

        $entity->setAttribute('label', 'New');
        $this->composite->save($entity);

        $updated = $this->db->query('SELECT * FROM composites WHERE user_id = 1 AND role_id = 2');
        self::assertSame('New', $updated[0]['label']);
    }

    public function testDeleteCompositeEntity(): void
    {
        $this->exec("INSERT INTO composites (user_id, role_id, label) VALUES (1, 2, 'Admin')");
        $row = $this->db->query('SELECT * FROM composites WHERE user_id = 1 AND role_id = 2');
        $entity = $this->composite->hydrateTracked($row[0]);

        $this->composite->delete($entity);

        $rows = $this->db->query('SELECT * FROM composites WHERE user_id = 1 AND role_id = 2');
        self::assertCount(0, $rows);
    }

    // ─── Relations — HasMany ─────────────────────────────────────────────────

    public function testHasManyEagerLoadWithRelation(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post A')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post B')");

        $users = $this->users->query()->with('posts')->get();

        self::assertCount(1, $users);
        $posts = $users[0]->getAttribute('posts');
        self::assertIsArray($posts);
        self::assertCount(2, $posts);
    }

    public function testHasManyLazyLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post A')");

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $relation = $this->users->posts($user);
        $posts = $relation->getResults();

        self::assertCount(1, $posts);
        self::assertSame('Post A', $posts[0]->getAttribute('title'));
    }

    // ─── Relations — HasOne ──────────────────────────────────────────────────

    public function testHasOneEagerLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO profiles (user_id, bio) VALUES (1, 'Hello')");

        $users = $this->users->query()->with('profile')->get();

        self::assertCount(1, $users);
        $profile = $users[0]->getAttribute('profile');
        self::assertInstanceOf(ProfileEntity::class, $profile);
        self::assertSame('Hello', $profile->getAttribute('bio'));
    }

    public function testHasOneLazyLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO profiles (user_id, bio) VALUES (1, 'Hello')");

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $profile = $this->users->profile($user)->getResult();

        self::assertNotNull($profile);
        self::assertSame('Hello', $profile->getAttribute('bio'));
    }

    // ─── Relations — BelongsTo ───────────────────────────────────────────────

    public function testBelongsToEagerLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post A')");

        $postsWithUser = $this->posts->query()->with('user')->get();

        self::assertCount(1, $postsWithUser);
        $user = $postsWithUser[0]->getAttribute('user');
        self::assertInstanceOf(UserEntity::class, $user);
        self::assertSame('Alice', $user->getAttribute('name'));
    }

    public function testBelongsToLazyLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post A')");

        $post = $this->posts->find(1);
        self::assertNotNull($post);

        $user = $this->posts->user($post)->getResult();

        self::assertNotNull($user);
        self::assertSame('Alice', $user->getAttribute('name'));
    }

    // ─── Relations — BelongsToMany ───────────────────────────────────────────

    public function testBelongsToManyEagerLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO tags (name) VALUES ('PHP')");
        $this->exec("INSERT INTO tags (name) VALUES ('ORM')");
        $this->exec('INSERT INTO user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO user_tags (user_id, tag_id) VALUES (1, 2)');

        $users = $this->users->query()->with('tags')->get();

        self::assertCount(1, $users);
        $userTags = $users[0]->getAttribute('tags');
        self::assertIsArray($userTags);
        self::assertCount(2, $userTags);
    }

    public function testBelongsToManyLazyLoad(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO tags (name) VALUES ('PHP')");
        $this->exec('INSERT INTO user_tags (user_id, tag_id) VALUES (1, 1)');

        $user = $this->users->find(1);
        self::assertNotNull($user);

        $tags = $this->users->tags($user)->getResults();

        self::assertCount(1, $tags);
        self::assertSame('PHP', $tags[0]->getAttribute('name'));
    }

    // ─── withCount() ─────────────────────────────────────────────────────────

    public function testWithCountAddsCountAttribute(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post A')");
        $this->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post B')");

        $users = $this->users->query()->withCount('posts')->get();

        self::assertCount(1, $users);
        self::assertSame(2, $users[0]->getAttribute('posts_count'));
    }

    // ─── findWhereIn() ───────────────────────────────────────────────────────

    public function testFindWhereInReturnsMatchingEntities(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");
        $this->exec("INSERT INTO users (name) VALUES ('Carol')");

        $users = $this->users->findWhereIn('id', [1, 3]);

        self::assertCount(2, $users);
    }

    public function testFindWhereInReturnsEmptyForEmptyInput(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");

        self::assertSame([], $this->users->findWhereIn('id', []));
    }

    // ─── hydrateOne() ────────────────────────────────────────────────────────

    public function testHydrateOneCreatesEntityWithoutTracking(): void
    {
        $entity = $this->users->hydrateOne(['id' => 99, 'name' => 'Ghost']);

        self::assertInstanceOf(UserEntity::class, $entity);
        self::assertSame('Ghost', $entity->getAttribute('name'));
    }

    // ─── Relation key accessors ───────────────────────────────────────────────

    public function testHasManyGetForeignKeyAndLocalKey(): void
    {
        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->posts($user);

        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function testHasOneGetForeignKeyAndLocalKey(): void
    {
        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->profile($user);

        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function testBelongsToGetForeignKeyAndLocalKey(): void
    {
        $post = $this->posts->hydrateOne(['id' => 1, 'user_id' => 1, 'title' => 'Post A']);
        $relation = $this->posts->user($post);

        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    public function testBelongsToManyGetForeignKeyAndLocalKey(): void
    {
        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->tags($user);

        self::assertSame('user_id', $relation->getForeignKey());
        self::assertSame('id', $relation->getLocalKey());
    }

    // ─── countFor() ──────────────────────────────────────────────────────────

    public function testHasOneCountForReturnsCountPerOwner(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO profiles (user_id, bio) VALUES (1, 'Bio A')");

        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->profile($user);

        $counts = $relation->countFor([1]);
        self::assertSame(1, $counts[1] ?? $counts['1']);
    }

    public function testHasOneCountForReturnsEmptyForEmptyInput(): void
    {
        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->profile($user);

        self::assertSame([], $relation->countFor([]));
    }

    public function testBelongsToCountForReturnsCountPerRelatedKey(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO users (name) VALUES ('Bob')");

        $post = $this->posts->hydrateOne(['id' => 1, 'user_id' => 1, 'title' => 'Post A']);
        $relation = $this->posts->user($post);

        // countFor on BelongsTo counts related-entity rows by their PK
        $counts = $relation->countFor([1, 2]);
        self::assertSame(1, $counts[1] ?? $counts['1']);
        self::assertSame(1, $counts[2] ?? $counts['2']);
    }

    public function testBelongsToCountForReturnsEmptyForEmptyInput(): void
    {
        $post = $this->posts->hydrateOne(['id' => 1, 'user_id' => 1, 'title' => 'Post A']);
        $relation = $this->posts->user($post);

        self::assertSame([], $relation->countFor([]));
    }

    public function testBelongsToManyCountForReturnsCountPerOwner(): void
    {
        $this->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->exec("INSERT INTO tags (name) VALUES ('PHP')");
        $this->exec("INSERT INTO tags (name) VALUES ('ORM')");
        $this->exec('INSERT INTO user_tags (user_id, tag_id) VALUES (1, 1)');
        $this->exec('INSERT INTO user_tags (user_id, tag_id) VALUES (1, 2)');

        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->tags($user);

        $counts = $relation->countFor([1]);
        self::assertSame(2, $counts[1] ?? $counts['1']);
    }

    public function testBelongsToManyCountForReturnsEmptyForEmptyInput(): void
    {
        $user = $this->users->hydrateOne(['id' => 1, 'name' => 'Alice']);
        $relation = $this->users->tags($user);

        self::assertSame([], $relation->countFor([]));
    }

    // ─── incrementColumn() ───────────────────────────────────────────────────

    public function testIncrementColumnByDefaultAmount(): void
    {
        $this->exec('INSERT INTO counters (count) VALUES (10)');
        $entity = $this->counters->find(1);
        assert($entity instanceof CounterEntity);

        $affected = $this->counters->incrementColumn($entity, 'count');

        self::assertSame(1, $affected);
        self::assertSame(11, $entity->getAttribute('count'));

        $row = $this->counters->find(1);
        assert($row instanceof CounterEntity);
        self::assertSame(11, $row->getAttribute('count'));
    }

    public function testIncrementColumnByCustomAmount(): void
    {
        $this->exec('INSERT INTO counters (count) VALUES (5)');
        $entity = $this->counters->find(1);
        assert($entity instanceof CounterEntity);

        $this->counters->incrementColumn($entity, 'count', 7);

        self::assertSame(12, $entity->getAttribute('count'));
    }

    public function testIncrementColumnDoesNotCauseSubsequentSaveToOverwrite(): void
    {
        $this->exec('INSERT INTO counters (count) VALUES (0)');
        $entity = $this->counters->find(1);
        assert($entity instanceof CounterEntity);

        $this->counters->incrementColumn($entity, 'count', 3);

        // save() with no other changes must be a no-op — does not revert count to 0
        $this->counters->save($entity);

        $row = $this->counters->find(1);
        assert($row instanceof CounterEntity);
        self::assertSame(3, $row->getAttribute('count'));
    }
}
