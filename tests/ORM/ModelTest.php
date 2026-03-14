<?php

declare(strict_types=1);

namespace Tests\ORM;

use EzPhp\Database\Database;
use EzPhp\Exceptions\EzPhpException;
use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\QueryBuilder;
use EzPhp\Orm\Relations\BelongsTo;
use EzPhp\Orm\Relations\BelongsToMany;
use EzPhp\Orm\Relations\HasMany;
use EzPhp\Orm\Relations\HasOne;
use EzPhp\Orm\Relations\Relation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\ModelTestCase;

// ---------------------------------------------------------------------------
// Test models
// ---------------------------------------------------------------------------

/**
 * @property int    $id
 * @property string $name
 * @property int    $active
 */
final class TestUser extends Model
{
    protected static string $table = 'users';

    /** @var list<string> */
    protected static array $fillable = ['name', 'active'];

    /**
     * @return HasMany
     */
    public function posts(): HasMany
    {
        return $this->hasMany(TestPost::class, 'user_id');
    }

    /**
     * @return HasOne
     */
    public function profile(): HasOne
    {
        return $this->hasOne(TestProfile::class, 'user_id');
    }

    /**
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(TestRole::class, 'user_roles', 'user_id', 'role_id');
    }
}

/**
 * @property int    $id
 * @property string $title
 * @property int    $user_id
 */
final class TestPost extends Model
{
    protected static string $table = 'posts';

    /** @var list<string> */
    protected static array $fillable = ['title', 'user_id'];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }
}

/**
 * @property int    $id
 * @property string $bio
 * @property int    $user_id
 */
final class TestProfile extends Model
{
    protected static string $table = 'profiles';

    /** @var list<string> */
    protected static array $fillable = ['bio', 'user_id'];
}

/**
 * @property int    $id
 * @property string $name
 */
final class TestRole extends Model
{
    protected static string $table = 'roles';

    /** @var list<string> */
    protected static array $fillable = ['name'];
}

/**
 * @property int    $id
 * @property string $title
 */
final class BlogPost extends Model
{
    // No $table set — auto-derived from class name: blog_posts
    /** @var list<string> */
    protected static array $fillable = ['title'];
}

/**
 * @property int    $id
 * @property string $label
 */
final class UnfillableModel extends Model
{
    protected static string $table = 'tags';
    // Empty $fillable => all attributes allowed
}

/**
 * @property int    $id
 * @property string $name
 * @property string|null $created_at
 * @property string|null $updated_at
 */
final class TimestampedUser extends Model
{
    protected static string $table = 'ts_users';

    protected static bool $timestamps = true;

    /** @var list<string> */
    protected static array $fillable = ['name'];
}

/**
 * @property int    $id
 * @property string $name
 * @property string|null $deleted_at
 */
final class SoftUser extends Model
{
    protected static string $table = 'soft_users';

    protected static bool $softDeletes = true;

    /** @var list<string> */
    protected static array $fillable = ['name'];
}

/**
 * @property int    $id
 * @property string $name
 * @property string $secret
 */
final class GuardedModel extends Model
{
    protected static string $table = 'guarded';

    /** @var list<string> */
    protected static array $guarded = ['id', 'secret'];
}

/**
 * @property int    $id
 * @property int    $active
 * @property float  $score
 * @property mixed  $data
 */
final class CastedModel extends Model
{
    protected static string $table = 'casted';

    /** @var list<string> */
    protected static array $fillable = ['active', 'score', 'data'];

    /** @var array<string, string> */
    protected static array $casts = ['active' => 'bool', 'score' => 'float', 'data' => 'array'];
}

/**
 * @property int    $id
 * @property string $name
 */
final class HookedModel extends Model
{
    protected static string $table = 'hooked';

    /** @var list<string> */
    protected static array $fillable = ['name'];

    public int $beforeSaveCallCount = 0;

    public int $afterCreateCallCount = 0;

    public int $beforeUpdateCallCount = 0;

    public int $afterUpdateCallCount = 0;

    public int $beforeDeleteCallCount = 0;

    public int $afterDeleteCallCount = 0;

    /**
     * @return void
     */
    protected function beforeSave(): void
    {
        $this->beforeSaveCallCount++;
    }

    /**
     * @return void
     */
    protected function afterCreate(): void
    {
        $this->afterCreateCallCount++;
    }

    /**
     * @return void
     */
    protected function beforeUpdate(): void
    {
        $this->beforeUpdateCallCount++;
    }

    /**
     * @return void
     */
    protected function afterUpdate(): void
    {
        $this->afterUpdateCallCount++;
    }

    /**
     * @return void
     */
    protected function beforeDelete(): void
    {
        $this->beforeDeleteCallCount++;
    }

    /**
     * @return void
     */
    protected function afterDelete(): void
    {
        $this->afterDeleteCallCount++;
    }
}

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

/**
 * Class ModelTest
 *
 * @package Tests\Database\ORM
 */
#[CoversClass(Model::class)]
#[CoversClass(ModelQueryBuilder::class)]
#[UsesClass(Database::class)]
#[UsesClass(QueryBuilder::class)]
#[UsesClass(Relation::class)]
#[UsesClass(HasMany::class)]
#[UsesClass(HasOne::class)]
#[UsesClass(BelongsTo::class)]
#[UsesClass(BelongsToMany::class)]
final class ModelTest extends ModelTestCase
{
    /**
     * @return void
     */
    protected function setUpDatabase(): void
    {
        $this->db->query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1)');
        $this->db->query("INSERT INTO users (name, active) VALUES ('Alice', 1)");
        $this->db->query("INSERT INTO users (name, active) VALUES ('Bob', 0)");
        $this->db->query("INSERT INTO users (name, active) VALUES ('Charlie', 1)");

        $this->db->query('CREATE TABLE blog_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)');
        $this->db->query("INSERT INTO blog_posts (title) VALUES ('Hello World')");

        $this->db->query('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');

        $this->db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, user_id INTEGER NOT NULL)');
        $this->db->query("INSERT INTO posts (title, user_id) VALUES ('Post A', 1)");
        $this->db->query("INSERT INTO posts (title, user_id) VALUES ('Post B', 1)");
        $this->db->query("INSERT INTO posts (title, user_id) VALUES ('Post C', 2)");

        $this->db->query('CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, bio TEXT NOT NULL, user_id INTEGER NOT NULL)');
        $this->db->query("INSERT INTO profiles (bio, user_id) VALUES ('Alice bio', 1)");

        $this->db->query('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->db->query("INSERT INTO roles (name) VALUES ('admin')");
        $this->db->query("INSERT INTO roles (name) VALUES ('editor')");

        $this->db->query('CREATE TABLE user_roles (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL)');
        $this->db->query('INSERT INTO user_roles (user_id, role_id) VALUES (1, 1)');
        $this->db->query('INSERT INTO user_roles (user_id, role_id) VALUES (1, 2)');
        $this->db->query('INSERT INTO user_roles (user_id, role_id) VALUES (2, 2)');

        $this->db->query('CREATE TABLE ts_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        $this->db->query('CREATE TABLE soft_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->db->query("INSERT INTO soft_users (name) VALUES ('Alive')");
        $this->db->query("INSERT INTO soft_users (name) VALUES ('AlsoAlive')");

        $this->db->query('CREATE TABLE guarded (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, secret TEXT NOT NULL DEFAULT "")');

        $this->db->query('CREATE TABLE casted (id INTEGER PRIMARY KEY AUTOINCREMENT, active INTEGER NOT NULL DEFAULT 0, score REAL NOT NULL DEFAULT 0, data TEXT NULL)');

        $this->db->query('CREATE TABLE hooked (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }

    // =========================================================================
    // Existing tests (preserved)
    // =========================================================================

    /**
     * @return void
     */
    public function test_find_returns_model_instance(): void
    {
        $user = TestUser::find(1);

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertSame('Alice', $user->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_find_returns_null_for_missing_id(): void
    {
        $this->assertNull(TestUser::find(999));
    }

    /**
     * @return void
     */
    public function test_all_returns_all_records(): void
    {
        $users = TestUser::all();

        $this->assertCount(3, $users);
        $this->assertInstanceOf(TestUser::class, $users[0]);
    }

    /**
     * @return void
     */
    public function test_where_filters_records(): void
    {
        $users = TestUser::where('active', 1)->get();

        $this->assertCount(2, $users);
    }

    /**
     * @return void
     */
    public function test_where_with_operator(): void
    {
        $this->assertCount(2, TestUser::where('id', '>', 1)->get());
    }

    /**
     * @return void
     */
    public function test_where_first_returns_model(): void
    {
        $user = TestUser::where('name', 'Bob')->first();

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertSame('Bob', $user->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_where_first_returns_null_when_not_found(): void
    {
        $this->assertNull(TestUser::where('name', 'Nobody')->first());
    }

    /**
     * @return void
     */
    public function test_where_count_returns_correct_count(): void
    {
        $this->assertSame(2, TestUser::where('active', 1)->count());
    }

    /**
     * @return void
     */
    public function test_where_order_by_works(): void
    {
        $users = TestUser::where('active', 1)->orderBy('name', 'desc')->get();

        $this->assertSame('Charlie', $users[0]->getAttribute('name'));
        $this->assertSame('Alice', $users[1]->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_where_limit_works(): void
    {
        $this->assertCount(1, TestUser::where('active', 1)->limit(1)->get());
    }

    /**
     * @return void
     */
    public function test_where_offset_works(): void
    {
        $users = TestUser::where('active', 1)->orderBy('id')->offset(1)->get();

        $this->assertCount(1, $users);
        $this->assertSame('Charlie', $users[0]->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_save_inserts_new_record(): void
    {
        $user = new TestUser(['name' => 'Dave', 'active' => 1]);
        $this->assertTrue($user->save());
        $this->assertSame(4, $this->db->table('users')->count());
        $this->assertNotNull($user->getAttribute('id'));
    }

    /**
     * @return void
     */
    public function test_save_updates_existing_record(): void
    {
        $user = TestUser::find(2);
        $this->assertNotNull($user);
        $user->setAttribute('active', 1);
        $this->assertTrue($user->save());

        $row = $this->db->table('users')->where('id', 2)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row['active']);
    }

    /**
     * @return void
     */
    public function test_create_inserts_and_returns_model(): void
    {
        $user = TestUser::create(['name' => 'Eve', 'active' => 0]);

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertSame('Eve', $user->getAttribute('name'));
        $this->assertNotNull($user->getAttribute('id'));
        $this->assertSame(4, $this->db->table('users')->count());
    }

    /**
     * @return void
     */
    public function test_delete_removes_record(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);
        $this->assertTrue($user->delete());
        $this->assertSame(2, $this->db->table('users')->count());
    }

    /**
     * @return void
     */
    public function test_delete_returns_false_without_id(): void
    {
        $user = new TestUser(['name' => 'Ghost', 'active' => 1]);
        $this->assertFalse($user->delete());
    }

    /**
     * @return void
     */
    public function test_fillable_prevents_mass_assignment(): void
    {
        $user = new TestUser(['name' => 'Alice', 'active' => 1, 'id' => 99]);

        $this->assertNull($user->getAttribute('id'));
        $this->assertSame('Alice', $user->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_empty_fillable_allows_all_attributes(): void
    {
        $tag = new UnfillableModel(['id' => 5, 'label' => 'php']);

        $this->assertSame(5, $tag->getAttribute('id'));
        $this->assertSame('php', $tag->getAttribute('label'));
    }

    /**
     * @return void
     */
    public function test_get_attributes_returns_all(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);
        $attrs = $user->getAttributes();

        $this->assertArrayHasKey('id', $attrs);
        $this->assertArrayHasKey('name', $attrs);
        $this->assertArrayHasKey('active', $attrs);
    }

    /**
     * @return void
     */
    public function test_magic_get_returns_attribute(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);
        $this->assertSame('Alice', $user->name);
    }

    /**
     * @return void
     */
    public function test_magic_set_sets_attribute(): void
    {
        $user = new TestUser(['name' => 'Alice', 'active' => 1]);
        $user->name = 'Alicia';

        $this->assertSame('Alicia', $user->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_magic_isset_returns_true_for_set_attribute(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);
        $this->assertTrue(isset($user->name));
        $this->assertFalse(isset($user->nonexistent));
    }

    /**
     * @return void
     */
    public function test_auto_table_name_derived_from_class_name(): void
    {
        $post = BlogPost::find(1);
        $this->assertNotNull($post);
        $this->assertSame('Hello World', $post->getAttribute('title'));
    }

    /**
     * @return void
     */
    public function test_no_database_throws_exception(): void
    {
        Model::resetDatabase();

        $this->expectException(EzPhpException::class);
        TestUser::find(1);
    }

    /**
     * @return void
     */
    public function test_from_raw_bypasses_fillable(): void
    {
        $user = TestUser::fromRaw(['id' => 42, 'name' => 'Test', 'active' => 1]);

        $this->assertSame(42, $user->getAttribute('id'));
        $this->assertSame('Test', $user->getAttribute('name'));
    }

    // =========================================================================
    // Timestamps
    // =========================================================================

    /**
     * @return void
     */
    public function test_timestamps_are_set_on_insert(): void
    {
        $user = TimestampedUser::create(['name' => 'Timestamped']);

        $this->assertNotNull($user->getAttribute('created_at'));
        $this->assertNotNull($user->getAttribute('updated_at'));
    }

    /**
     * @return void
     */
    public function test_updated_at_is_refreshed_on_update(): void
    {
        $user = TimestampedUser::create(['name' => 'First']);
        $createdAt = $user->getAttribute('created_at');

        // Ensure time difference by bumping updated_at manually first
        sleep(1);

        $user->setAttribute('name', 'Second');
        $user->save();

        $this->assertSame($createdAt, $user->getAttribute('created_at'));
        $this->assertNotNull($user->getAttribute('updated_at'));
    }

    // =========================================================================
    // Dirty Tracking
    // =========================================================================

    /**
     * @return void
     */
    public function test_is_dirty_returns_false_after_load(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $this->assertFalse($user->isDirty());
    }

    /**
     * @return void
     */
    public function test_is_dirty_returns_true_after_attribute_change(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $user->setAttribute('name', 'Changed');

        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->isDirty('name'));
        $this->assertFalse($user->isDirty('active'));
    }

    /**
     * @return void
     */
    public function test_get_dirty_returns_changed_fields(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $user->setAttribute('name', 'Changed');
        $dirty = $user->getDirty();

        $this->assertArrayHasKey('name', $dirty);
        $this->assertArrayNotHasKey('active', $dirty);
    }

    /**
     * @return void
     */
    public function test_is_clean_after_save(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $user->setAttribute('name', 'Changed');
        $this->assertTrue($user->isDirty());

        $user->save();

        $this->assertFalse($user->isDirty());
    }

    /**
     * @return void
     */
    public function test_save_only_updates_dirty_fields(): void
    {
        $user = TestUser::find(2);
        $this->assertNotNull($user);

        // Only change active
        $user->setAttribute('active', 1);
        $user->save();

        $row = $this->db->table('users')->where('id', 2)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row['active']);
        $this->assertSame('Bob', $row['name']);
    }

    // =========================================================================
    // $guarded
    // =========================================================================

    /**
     * @return void
     */
    public function test_guarded_prevents_mass_assignment_for_protected_keys(): void
    {
        $model = new GuardedModel(['name' => 'Test', 'id' => 99, 'secret' => 'hidden']);

        $this->assertNull($model->getAttribute('id'));
        $this->assertNull($model->getAttribute('secret'));
        $this->assertSame('Test', $model->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_guarded_allows_non_guarded_keys(): void
    {
        $model = new GuardedModel(['name' => 'Test']);

        $this->assertSame('Test', $model->getAttribute('name'));
    }

    // =========================================================================
    // $casts
    // =========================================================================

    /**
     * @return void
     */
    public function test_cast_bool_returns_boolean(): void
    {
        $this->db->query('INSERT INTO casted (active, score) VALUES (1, 3.5)');
        $model = CastedModel::find(1);
        $this->assertNotNull($model);

        $this->assertTrue($model->getAttribute('active'));
    }

    /**
     * @return void
     */
    public function test_cast_float_returns_float(): void
    {
        $this->db->query('INSERT INTO casted (active, score) VALUES (1, 3.5)');
        $model = CastedModel::find(1);
        $this->assertNotNull($model);

        $this->assertSame(3.5, $model->getAttribute('score'));
    }

    /**
     * @return void
     */
    public function test_cast_array_decodes_json_on_read(): void
    {
        $this->db->query("INSERT INTO casted (active, score, data) VALUES (0, 0, '{\"key\":\"value\"}')");
        $model = CastedModel::find(1);
        $this->assertNotNull($model);

        $this->assertSame(['key' => 'value'], $model->getAttribute('data'));
    }

    /**
     * @return void
     */
    public function test_cast_array_encodes_to_json_on_save(): void
    {
        $model = CastedModel::create(['active' => true, 'score' => 1.0, 'data' => ['foo' => 'bar']]);

        $row = $this->db->table('casted')->where('id', $model->getAttribute('id'))->first();
        $this->assertNotNull($row);
        $this->assertSame('{"foo":"bar"}', $row['data']);
    }

    /**
     * @return void
     */
    public function test_dirty_tracking_works_with_casted_array(): void
    {
        $this->db->query("INSERT INTO casted (active, score, data) VALUES (0, 0, '{\"key\":\"value\"}')");
        $model = CastedModel::find(1);
        $this->assertNotNull($model);

        // Set the same logical value — should not be dirty
        $model->setAttribute('data', ['key' => 'value']);
        $this->assertFalse($model->isDirty('data'));

        // Change the value — should be dirty
        $model->setAttribute('data', ['key' => 'changed']);
        $this->assertTrue($model->isDirty('data'));
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    /**
     * @return void
     */
    public function test_before_save_hook_is_called_on_insert(): void
    {
        $model = new HookedModel(['name' => 'Test']);
        $model->save();

        $this->assertSame(1, $model->beforeSaveCallCount);
        $this->assertSame(1, $model->afterCreateCallCount);
        $this->assertSame(0, $model->beforeUpdateCallCount);
    }

    /**
     * @return void
     */
    public function test_hooks_are_called_on_update(): void
    {
        $model = HookedModel::create(['name' => 'Original']);
        $model->setAttribute('name', 'Updated');
        $model->save();

        $this->assertSame(2, $model->beforeSaveCallCount); // once for insert, once for update
        $this->assertSame(1, $model->beforeUpdateCallCount);
        $this->assertSame(1, $model->afterUpdateCallCount);
    }

    /**
     * @return void
     */
    public function test_delete_hooks_are_called(): void
    {
        $model = HookedModel::create(['name' => 'ToDelete']);
        $model->delete();

        $this->assertSame(1, $model->beforeDeleteCallCount);
        $this->assertSame(1, $model->afterDeleteCallCount);
    }

    // =========================================================================
    // Soft Deletes
    // =========================================================================

    /**
     * @return void
     */
    public function test_soft_delete_sets_deleted_at(): void
    {
        $user = SoftUser::find(1);
        $this->assertNotNull($user);

        $user->delete();

        $this->assertNotNull($user->getAttribute('deleted_at'));
        $this->assertTrue($user->trashed());
    }

    /**
     * @return void
     */
    public function test_soft_deleted_records_excluded_from_default_query(): void
    {
        $user = SoftUser::find(1);
        $this->assertNotNull($user);
        $user->delete();

        $all = SoftUser::all();
        $this->assertCount(1, $all);
        $this->assertSame('AlsoAlive', $all[0]->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_with_trashed_includes_soft_deleted(): void
    {
        $user = SoftUser::find(1);
        $this->assertNotNull($user);
        $user->delete();

        $all = SoftUser::withTrashed()->get();
        $this->assertCount(2, $all);
    }

    /**
     * @return void
     */
    public function test_only_trashed_returns_only_deleted(): void
    {
        $user = SoftUser::find(1);
        $this->assertNotNull($user);
        $user->delete();

        $deleted = SoftUser::onlyTrashed()->get();
        $this->assertCount(1, $deleted);
        $this->assertSame('Alive', $deleted[0]->getAttribute('name'));
    }

    /**
     * @return void
     */
    public function test_restore_clears_deleted_at(): void
    {
        $user = SoftUser::find(1);
        $this->assertNotNull($user);
        $user->delete();

        $this->assertTrue($user->trashed());
        $user->restore();

        $this->assertFalse($user->trashed());
        $this->assertCount(2, SoftUser::all());
    }

    /**
     * @return void
     */
    public function test_force_delete_removes_record_permanently(): void
    {
        $user = SoftUser::find(1);
        $this->assertNotNull($user);

        $user->forceDelete();

        $this->assertCount(1, SoftUser::withTrashed()->get());
    }

    // =========================================================================
    // Relationships — HasMany
    // =========================================================================

    /**
     * @return void
     */
    public function test_has_many_lazy_returns_related_models(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $posts = $user->posts()->get();

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(TestPost::class, $posts[0]);
    }

    /**
     * @return void
     */
    public function test_has_many_lazy_via_magic_get(): void
    {
        // Accessing via __get triggers a lazy load (returns HasMany, not array)
        // Relation objects are not auto-resolved via __get; users call ->get()
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $this->assertCount(2, $user->posts()->get());
    }

    // =========================================================================
    // Relationships — HasOne
    // =========================================================================

    /**
     * @return void
     */
    public function test_has_one_returns_single_model(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $profile = $user->profile()->getResults();

        $this->assertInstanceOf(TestProfile::class, $profile);
        $this->assertSame('Alice bio', $profile->getAttribute('bio'));
    }

    /**
     * @return void
     */
    public function test_has_one_returns_null_when_missing(): void
    {
        $user = TestUser::find(2);
        $this->assertNotNull($user);

        $this->assertNull($user->profile()->getResults());
    }

    // =========================================================================
    // Relationships — BelongsTo
    // =========================================================================

    /**
     * @return void
     */
    public function test_belongs_to_returns_related_model(): void
    {
        $post = TestPost::find(1);
        $this->assertNotNull($post);

        $user = $post->user()->getResults();

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertSame('Alice', $user->getAttribute('name'));
    }

    // =========================================================================
    // Relationships — BelongsToMany
    // =========================================================================

    /**
     * @return void
     */
    public function test_belongs_to_many_returns_related_models(): void
    {
        $user = TestUser::find(1);
        $this->assertNotNull($user);

        $roles = $user->roles()->get();

        $this->assertCount(2, $roles);
        $this->assertInstanceOf(TestRole::class, $roles[0]);
    }

    /**
     * @return void
     */
    public function test_belongs_to_many_scoped_to_owner(): void
    {
        $user = TestUser::find(2);
        $this->assertNotNull($user);

        $roles = $user->roles()->get();

        $this->assertCount(1, $roles);
        $this->assertSame('editor', $roles[0]->getAttribute('name'));
    }

    // =========================================================================
    // Eager Loading
    // =========================================================================

    /**
     * @return void
     */
    public function test_eager_load_has_many_avoids_n_plus_one(): void
    {
        $users = TestUser::with('posts')->get();

        $this->assertCount(3, $users);

        $alice = $users[0];
        $posts = $alice->getAttribute('posts');

        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);
        $this->assertInstanceOf(TestPost::class, $posts[0]);
    }

    /**
     * @return void
     */
    public function test_eager_load_has_one(): void
    {
        $users = TestUser::with('profile')->get();

        $this->assertCount(3, $users);

        $alice = $users[0];
        $profile = $alice->getAttribute('profile');
        $this->assertInstanceOf(TestProfile::class, $profile);

        $bob = $users[1];
        $this->assertNull($bob->getAttribute('profile'));
    }

    /**
     * @return void
     */
    public function test_eager_load_belongs_to(): void
    {
        $posts = TestPost::with('user')->get();

        $this->assertCount(3, $posts);

        foreach ($posts as $post) {
            $this->assertInstanceOf(TestUser::class, $post->getAttribute('user'));
        }
    }

    /**
     * @return void
     */
    public function test_eager_load_belongs_to_many(): void
    {
        $users = TestUser::with('roles')->get();

        $this->assertCount(3, $users);

        $alice = $users[0];
        $roles = $alice->getAttribute('roles');

        $this->assertIsArray($roles);
        $this->assertCount(2, $roles);

        $charlie = $users[2];
        $this->assertSame([], $charlie->getAttribute('roles'));
    }

    // =========================================================================
    // whereIn (static)
    // =========================================================================

    /**
     * @return void
     */
    public function test_where_in_filters_by_ids(): void
    {
        $users = TestUser::whereIn('id', [1, 3])->get();

        $this->assertCount(2, $users);
    }

    // =========================================================================
    // query() and getTable()
    // =========================================================================

    /**
     * @return void
     */
    public function test_query_returns_model_query_builder(): void
    {
        $this->assertInstanceOf(ModelQueryBuilder::class, TestUser::query());
    }

    /**
     * @return void
     */
    public function test_get_table_returns_resolved_table_name(): void
    {
        $this->assertSame('users', TestUser::getTable());
        $this->assertSame('blog_posts', BlogPost::getTable());
    }
}
