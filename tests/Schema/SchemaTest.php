<?php

declare(strict_types=1);

namespace Tests\Schema;

use EzPhp\Orm\Schema\Blueprint;
use EzPhp\Orm\Schema\ColumnDefinition;
use EzPhp\Orm\Schema\ForeignKeyDefinition;
use EzPhp\Orm\Schema\Schema;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\PdoDatabase;
use Tests\TestCase;

/**
 * Class SchemaTest
 *
 * @package Tests\Database\Schema
 */
#[CoversClass(Schema::class)]
#[UsesClass(Blueprint::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(ForeignKeyDefinition::class)]
final class SchemaTest extends TestCase
{
    private PdoDatabase $db;

    private Schema $schema;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new PdoDatabase('sqlite::memory:');
        $this->schema = new Schema($this->db);
    }

    /**
     * @return void
     */
    public function test_create_creates_table(): void
    {
        $this->schema->create('posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
        });

        $this->assertTrue($this->schema->hasTable('posts'));
    }

    /**
     * @return void
     */
    public function test_has_table_returns_false_when_table_missing(): void
    {
        $this->assertFalse($this->schema->hasTable('nonexistent'));
    }

    /**
     * @return void
     */
    public function test_drop_removes_table(): void
    {
        $this->schema->create('tmp', function (Blueprint $t): void {
            $t->id();
        });

        $this->schema->drop('tmp');

        $this->assertFalse($this->schema->hasTable('tmp'));
    }

    /**
     * @return void
     */
    public function test_drop_if_exists_does_not_throw_when_table_missing(): void
    {
        $this->schema->dropIfExists('nonexistent');
        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     */
    public function test_created_table_accepts_inserts(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        $this->db->query("INSERT INTO users (name, active, created_at, updated_at) VALUES ('Alice', 1, NULL, NULL)");
        $rows = $this->db->query('SELECT name FROM users');

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_nullable_column_accepts_null(): void
    {
        $this->schema->create('posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->text('body')->nullable();
        });

        $this->db->query("INSERT INTO posts (title, body) VALUES ('Hello', NULL)");
        $rows = $this->db->query('SELECT body FROM posts');

        $this->assertNull($rows[0]['body']);
    }

    /**
     * @return void
     */
    public function test_unique_column_rejects_duplicates(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('email')->unique();
        });

        $this->db->query("INSERT INTO users (email) VALUES ('a@a.com')");

        $this->expectException(PDOException::class);
        $this->db->query("INSERT INTO users (email) VALUES ('a@a.com')");
    }

    // --- New column types ---

    /**
     * @return void
     */
    public function test_unsigned_integer_column(): void
    {
        $this->schema->create('products', function (Blueprint $t): void {
            $t->id();
            $t->unsignedInteger('stock')->default(0);
        });

        $this->db->query('INSERT INTO products (stock) VALUES (10)');
        $rows = $this->db->query('SELECT stock FROM products');

        $this->assertEquals(10, $rows[0]['stock']);
    }

    /**
     * @return void
     */
    public function test_float_column(): void
    {
        $this->schema->create('measurements', function (Blueprint $t): void {
            $t->id();
            $t->float('value');
        });

        $this->db->query('INSERT INTO measurements (value) VALUES (3.14)');
        $rows = $this->db->query('SELECT value FROM measurements');

        $this->assertEqualsWithDelta(3.14, $rows[0]['value'], 0.001);
    }

    /**
     * @return void
     */
    public function test_decimal_column(): void
    {
        $this->schema->create('prices', function (Blueprint $t): void {
            $t->id();
            $t->decimal('amount', 10, 2);
        });

        $this->db->query('INSERT INTO prices (amount) VALUES (99.99)');
        $rows = $this->db->query('SELECT amount FROM prices');

        $this->assertEqualsWithDelta(99.99, $rows[0]['amount'], 0.001);
    }

    /**
     * @return void
     */
    public function test_date_column(): void
    {
        $this->schema->create('events', function (Blueprint $t): void {
            $t->id();
            $t->date('event_date');
        });

        $this->db->query("INSERT INTO events (event_date) VALUES ('2026-03-12')");
        $rows = $this->db->query('SELECT event_date FROM events');

        $this->assertSame('2026-03-12', $rows[0]['event_date']);
    }

    /**
     * @return void
     */
    public function test_json_column(): void
    {
        $this->schema->create('configs', function (Blueprint $t): void {
            $t->id();
            $t->json('data');
        });

        $this->db->query('INSERT INTO configs (data) VALUES (\'{"key":"value"}\')');
        $rows = $this->db->query('SELECT data FROM configs');

        $this->assertSame('{"key":"value"}', $rows[0]['data']);
    }

    // --- ALTER TABLE ---

    /**
     * @return void
     */
    public function test_table_adds_column(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $this->schema->table('users', function (Blueprint $t): void {
            $t->string('email')->nullable();
        });

        $this->db->query("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");
        $rows = $this->db->query('SELECT email FROM users');

        $this->assertSame('bob@example.com', $rows[0]['email']);
    }

    /**
     * @return void
     */
    public function test_table_drops_column(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('temp_field')->nullable();
        });

        $this->schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('temp_field');
        });

        $this->db->query("INSERT INTO users (name) VALUES ('Alice')");
        $rows = $this->db->query('SELECT * FROM users');

        $this->assertArrayNotHasKey('temp_field', $rows[0]);
    }

    /**
     * @return void
     */
    public function test_table_renames_column(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('old_name');
        });

        $this->schema->table('users', function (Blueprint $t): void {
            $t->renameColumn('old_name', 'new_name');
        });

        $this->db->query("INSERT INTO users (new_name) VALUES ('Alice')");
        $rows = $this->db->query('SELECT new_name FROM users');

        $this->assertSame('Alice', $rows[0]['new_name']);
    }

    // --- Foreign Keys ---

    /**
     * @return void
     */
    public function test_create_with_foreign_key(): void
    {
        $this->db->getPdo()->exec('PRAGMA foreign_keys = ON');

        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $this->schema->create('posts', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->foreign('user_id')->references('id')->on('users');
        });

        $this->assertTrue($this->schema->hasTable('posts'));

        $this->db->query("INSERT INTO users (name) VALUES ('Alice')");
        $this->db->query('INSERT INTO posts (user_id) VALUES (1)');
        $rows = $this->db->query('SELECT user_id FROM posts');

        $this->assertEquals(1, $rows[0]['user_id']);
    }

    /**
     * @return void
     */
    public function test_foreign_key_rejects_invalid_reference(): void
    {
        $this->db->getPdo()->exec('PRAGMA foreign_keys = ON');

        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $this->schema->create('posts', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->foreign('user_id')->references('id')->on('users');
        });

        $this->expectException(PDOException::class);
        $this->db->query('INSERT INTO posts (user_id) VALUES (999)');
    }

    // --- Composite Indexes ---

    /**
     * @return void
     */
    public function test_composite_index_is_created(): void
    {
        $this->schema->create('orders', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id');
            $t->string('status');
            $t->index(['user_id', 'status'], 'orders_user_status_index');
        });

        $rows = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='index' AND name='orders_user_status_index'"
        );

        $this->assertCount(1, $rows);
    }

    /**
     * @return void
     */
    public function test_index_auto_generates_name(): void
    {
        $this->schema->create('orders', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id');
            $t->string('status');
            $t->index(['user_id', 'status']);
        });

        $rows = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='index' AND name='orders_user_id_status_index'"
        );

        $this->assertCount(1, $rows);
    }

    /**
     * @return void
     */
    public function test_single_column_index_via_string(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('email');
            $t->index('email', 'users_email_index');
        });

        $rows = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='index' AND name='users_email_index'"
        );

        $this->assertCount(1, $rows);
    }

    /**
     * @return void
     */
    public function test_index_added_via_table_alter(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
        });

        $this->schema->table('users', function (Blueprint $t): void {
            $t->index(['name', 'email'], 'users_name_email_index');
        });

        $rows = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='index' AND name='users_name_email_index'"
        );

        $this->assertCount(1, $rows);
    }

    // --- MySQL: INFORMATION_SCHEMA path ---

    /**
     * @return void
     */
    public function test_has_table_returns_false_for_missing_table_on_mysql(): void
    {
        $host = (string) getenv('DB_HOST');
        $database = (string) (getenv('DB_TESTING_DATABASE') ?: 'ez-php_testing');
        $username = (string) getenv('DB_USERNAME');
        $password = (string) getenv('DB_PASSWORD');

        if ($host === '') {
            $this->markTestSkipped('MySQL not available — set DB_HOST to run this test.');
        }

        $mysqlDb = new PdoDatabase("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
        $schema = new Schema($mysqlDb);

        $this->assertFalse($schema->hasTable('ez_php_schema_test_nonexistent_' . uniqid()));
    }

    // =========================================================================
    // Feature 15: Table Introspection
    // =========================================================================

    /**
     * @return void
     */
    public function test_has_column_returns_true_for_existing_column(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $this->assertTrue($this->schema->hasColumn('users', 'name'));
        $this->assertTrue($this->schema->hasColumn('users', 'id'));
    }

    /**
     * @return void
     */
    public function test_has_column_returns_false_for_nonexistent_column(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $this->assertFalse($this->schema->hasColumn('users', 'email'));
    }

    /**
     * @return void
     */
    public function test_get_columns_returns_column_definitions(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $columns = $this->schema->getColumns('users');

        $this->assertCount(2, $columns);
        $names = array_column($columns, 'name');
        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
    }

    /**
     * @return void
     */
    public function test_get_columns_includes_nullable_flag(): void
    {
        $this->schema->create('posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->text('body')->nullable();
        });

        $columns = $this->schema->getColumns('posts');
        $byName = [];

        foreach ($columns as $col) {
            $byName[$col['name']] = $col;
        }

        $this->assertFalse($byName['title']['nullable']);
        $this->assertTrue($byName['body']['nullable']);
    }

    /**
     * @return void
     */
    public function test_get_columns_returns_empty_for_nonexistent_table(): void
    {
        $columns = $this->schema->getColumns('nonexistent_table');

        $this->assertSame([], $columns);
    }

    // =========================================================================
    // Feature 16: Enum Columns (integration)
    // =========================================================================

    /**
     * @return void
     */
    public function test_enum_column_accepts_valid_value(): void
    {
        $this->schema->create('things', function (Blueprint $t): void {
            $t->id();
            $t->enum('status', ['active', 'inactive']);
        });

        $this->db->query("INSERT INTO things (status) VALUES ('active')");
        $rows = $this->db->query('SELECT status FROM things');

        $this->assertSame('active', $rows[0]['status']);
    }

    /**
     * @return void
     */
    public function test_enum_column_rejects_invalid_value(): void
    {
        $this->db->getPdo()->exec('PRAGMA ignore_check_constraints = OFF');

        $this->schema->create('things', function (Blueprint $t): void {
            $t->id();
            $t->enum('status', ['active', 'inactive']);
        });

        $this->expectException(PDOException::class);
        $this->db->query("INSERT INTO things (status) VALUES ('deleted')");
    }

    // =========================================================================
    // Step 11 — renameColumn() driver correctness
    // =========================================================================

    /**
     * SQLite 3.25+ is required for RENAME COLUMN. This test documents the
     * minimum version and ensures the running SQLite meets it.
     *
     * @return void
     */
    public function test_sqlite_version_supports_rename_column(): void
    {
        $version = $this->db->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $this->assertIsString($version);
        $this->assertTrue(
            version_compare($version, '3.25.0', '>='),
            "SQLite $version is too old for RENAME COLUMN (requires 3.25+)."
        );
    }

    /**
     * @return void
     */
    public function test_rename_table(): void
    {
        $this->schema->create('old_name', function (Blueprint $t): void {
            $t->id();
        });

        $this->schema->rename('old_name', 'new_name');

        $this->assertTrue($this->schema->hasTable('new_name'));
        $this->assertFalse($this->schema->hasTable('old_name'));
    }

    // =========================================================================
    // Step 12 — Schema dump
    // =========================================================================

    /**
     * @return void
     */
    public function test_dump_creates_file_with_create_table(): void
    {
        $this->schema->create('articles', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
        });

        $path = sys_get_temp_dir() . '/schema_dump_' . uniqid() . '.sql';

        try {
            $this->schema->dump($path);

            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString('CREATE TABLE', $content);
            $this->assertStringContainsString('articles', $content);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return void
     */
    public function test_dump_includes_indexes(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('email');
            $t->index('email', 'users_email_index');
        });

        $path = sys_get_temp_dir() . '/schema_dump_' . uniqid() . '.sql';

        try {
            $this->schema->dump($path);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString('CREATE INDEX', $content);
            $this->assertStringContainsString('users_email_index', $content);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return void
     */
    public function test_dump_multiple_tables_all_present(): void
    {
        $this->schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });

        $this->schema->create('posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
        });

        $path = sys_get_temp_dir() . '/schema_dump_' . uniqid() . '.sql';

        try {
            $this->schema->dump($path);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString('users', $content);
            $this->assertStringContainsString('posts', $content);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return void
     */
    public function test_dump_file_has_header_comment(): void
    {
        $path = sys_get_temp_dir() . '/schema_dump_' . uniqid() . '.sql';

        try {
            $this->schema->dump($path);
            $content = (string) file_get_contents($path);

            $this->assertStringStartsWith('-- Schema dump generated at', $content);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
