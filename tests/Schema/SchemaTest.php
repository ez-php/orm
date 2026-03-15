<?php

declare(strict_types=1);

namespace Tests\Schema;

use EzPhp\Database\Database;
use EzPhp\Orm\Schema\Blueprint;
use EzPhp\Orm\Schema\ColumnDefinition;
use EzPhp\Orm\Schema\ForeignKeyDefinition;
use EzPhp\Orm\Schema\Schema;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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
    private Database $db;

    private Schema $schema;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:', '', '');
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

        $mysqlDb = new Database("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
        $schema = new Schema($mysqlDb);

        $this->assertFalse($schema->hasTable('ez_php_schema_test_nonexistent_' . uniqid()));
    }
}
