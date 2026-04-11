<?php

declare(strict_types=1);

namespace Tests\Schema;

use EzPhp\Orm\Schema\Blueprint;
use EzPhp\Orm\Schema\ColumnDefinition;
use EzPhp\Orm\Schema\ForeignKeyDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class BlueprintTest
 *
 * @package Tests\Database\Schema
 */
#[CoversClass(Blueprint::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(ForeignKeyDefinition::class)]
final class BlueprintTest extends TestCase
{
    /**
     * @return void
     */
    public function test_id_generates_sqlite_primary_key(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->id();

        $this->assertSame('CREATE TABLE `t` (`id` INTEGER PRIMARY KEY)', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_id_generates_mysql_primary_key(): void
    {
        $bp = new Blueprint('mysql');
        $bp->id();

        $this->assertSame('CREATE TABLE `t` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY)', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_string_generates_varchar_on_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('name');

        $this->assertStringContainsString('`name` VARCHAR(255) NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_string_with_custom_length(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('slug', 100);

        $this->assertStringContainsString('`slug` VARCHAR(100) NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_string_generates_text_on_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->string('name');

        $this->assertStringContainsString('`name` TEXT NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_nullable_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('bio')->nullable();

        $this->assertStringContainsString('`bio` VARCHAR(255) NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_unique_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('email')->unique();

        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL UNIQUE', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_default_string_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('role')->default('user');

        $this->assertStringContainsString("`role` VARCHAR(255) NOT NULL DEFAULT 'user'", $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_default_int_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->integer('votes')->default(0);

        $this->assertStringContainsString('`votes` INTEGER NOT NULL DEFAULT 0', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_default_bool_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->boolean('active')->default(true);

        $this->assertStringContainsString('`active` TINYINT(1) NOT NULL DEFAULT 1', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_default_null_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('deleted_at')->nullable()->default(null);

        $this->assertStringContainsString('`deleted_at` VARCHAR(255) NULL DEFAULT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_default_string_with_single_quote_is_escaped(): void
    {
        $bp = new Blueprint('mysql');
        $bp->string('label')->default("it's");

        $this->assertStringContainsString("DEFAULT 'it''s'", $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_timestamps_adds_created_and_updated_at(): void
    {
        $bp = new Blueprint('mysql');
        $bp->id();
        $bp->timestamps();

        $sql = $bp->toCreateSql('t');

        $this->assertStringContainsString('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContainsString('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);
    }

    /**
     * @return void
     */
    public function test_text_column(): void
    {
        $bp = new Blueprint('mysql');
        $bp->text('body');

        $this->assertStringContainsString('`body` TEXT NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_big_integer_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->bigInteger('views');

        $this->assertStringContainsString('`views` BIGINT NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_big_integer_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->bigInteger('views');

        $this->assertStringContainsString('`views` INTEGER NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_boolean_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->boolean('active');

        $this->assertStringContainsString('`active` TINYINT(1) NOT NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_timestamp_column_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->timestamp('verified_at')->nullable();

        $this->assertStringContainsString('`verified_at` TIMESTAMP NULL', $bp->toCreateSql('t'));
    }

    /**
     * @return void
     */
    public function test_full_create_table_sql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->id();
        $bp->string('email')->unique();
        $bp->string('name');
        $bp->boolean('active')->default(true);
        $bp->timestamps();

        $sql = $bp->toCreateSql('users');

        $this->assertStringStartsWith('CREATE TABLE `users` (', $sql);
        $this->assertStringContainsString('`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL UNIQUE', $sql);
        $this->assertStringContainsString('`active` TINYINT(1) NOT NULL DEFAULT 1', $sql);
    }

    public function test_unsigned_integer_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->unsignedInteger('user_id');

        $this->assertStringContainsString('`user_id` INT UNSIGNED NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_unsigned_integer_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->unsignedInteger('user_id');

        $this->assertStringContainsString('`user_id` INTEGER NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_float_column(): void
    {
        $bp = new Blueprint('mysql');
        $bp->float('price');

        $this->assertStringContainsString('`price` FLOAT NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_decimal_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->decimal('amount', 10, 4);

        $this->assertStringContainsString('`amount` DECIMAL(10,4) NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_decimal_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->decimal('amount');

        $this->assertStringContainsString('`amount` NUMERIC(8,2) NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_date_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->date('birthday');

        $this->assertStringContainsString('`birthday` DATE NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_date_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->date('birthday');

        $this->assertStringContainsString('`birthday` TEXT NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_json_mysql(): void
    {
        $bp = new Blueprint('mysql');
        $bp->json('meta');

        $this->assertStringContainsString('`meta` JSON NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_json_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->json('meta');

        $this->assertStringContainsString('`meta` TEXT NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_boolean_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->boolean('active');

        $this->assertStringContainsString('`active` INTEGER NOT NULL', $bp->toCreateSql('t'));
    }

    public function test_timestamp_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->timestamp('verified_at')->nullable();

        $this->assertStringContainsString('`verified_at` TEXT NULL', $bp->toCreateSql('t'));
    }

    public function test_timestamps_sqlite(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->timestamps();

        $sql = $bp->toCreateSql('t');

        $this->assertStringContainsString('`created_at` TEXT NULL DEFAULT NULL', $sql);
        $this->assertStringContainsString('`updated_at` TEXT NULL DEFAULT NULL', $sql);
    }

    public function test_default_float_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->float('rate')->default(1.5);

        $this->assertStringContainsString('`rate` FLOAT NOT NULL DEFAULT 1.5', $bp->toCreateSql('t'));
    }

    public function test_default_false_bool_modifier(): void
    {
        $bp = new Blueprint('mysql');
        $bp->boolean('active')->default(false);

        $this->assertStringContainsString('`active` TINYINT(1) NOT NULL DEFAULT 0', $bp->toCreateSql('t'));
    }

    public function test_alter_mode_adds_column(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->string('nickname');

        $stmts = $bp->toAlterSql('users');

        $this->assertCount(1, $stmts);
        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN', $stmts[0]);
        $this->assertStringContainsString('`nickname` VARCHAR(255) NOT NULL', $stmts[0]);
    }

    public function test_drop_column(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->dropColumn('old_col');

        $stmts = $bp->toAlterSql('users');

        $this->assertCount(1, $stmts);
        $this->assertSame('ALTER TABLE `users` DROP COLUMN `old_col`', $stmts[0]);
    }

    public function test_rename_column(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->renameColumn('old_name', 'new_name');

        $stmts = $bp->toAlterSql('users');

        $this->assertCount(1, $stmts);
        $this->assertSame('ALTER TABLE `users` RENAME COLUMN `old_name` TO `new_name`', $stmts[0]);
    }

    public function test_foreign_key_in_create(): void
    {
        $bp = new Blueprint('mysql');
        $bp->unsignedInteger('user_id');
        $bp->foreign('user_id')->references('id')->on('users');

        $sql = $bp->toCreateSql('posts');

        $this->assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)', $sql);
    }

    public function test_foreign_key_in_alter(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->foreign('user_id')->references('id')->on('users');

        $stmts = $bp->toAlterSql('posts');

        $this->assertCount(1, $stmts);
        $this->assertStringContainsString('ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)', $stmts[0]);
    }

    public function test_foreign_key_skipped_in_sqlite_alter(): void
    {
        $bp = new Blueprint('sqlite', 'alter');
        $bp->foreign('user_id')->references('id')->on('users');

        $stmts = $bp->toAlterSql('posts');

        $this->assertCount(0, $stmts);
    }

    public function test_index_with_auto_name(): void
    {
        $bp = new Blueprint('mysql');
        $bp->id();
        $bp->index('email');

        $stmts = $bp->toIndexSql('users');

        $this->assertCount(1, $stmts);
        $this->assertStringContainsString('CREATE INDEX `users_email_index` ON `users` (`email`)', $stmts[0]);
    }

    public function test_index_with_custom_name(): void
    {
        $bp = new Blueprint('mysql');
        $bp->id();
        $bp->index(['first_name', 'last_name'], 'idx_full_name');

        $stmts = $bp->toIndexSql('users');

        $this->assertCount(1, $stmts);
        $this->assertSame('CREATE INDEX `idx_full_name` ON `users` (`first_name`, `last_name`)', $stmts[0]);
    }

    // =========================================================================
    // Feature 16: Enum Columns
    // =========================================================================

    /**
     * @return void
     */
    public function test_enum_mysql_generates_enum_type(): void
    {
        $bp = new Blueprint('mysql');
        $bp->enum('status', ['active', 'inactive']);

        $sql = $bp->toCreateSql('t');

        $this->assertStringContainsString("ENUM('active', 'inactive')", $sql);
    }

    /**
     * @return void
     */
    public function test_enum_sqlite_generates_check_constraint(): void
    {
        $bp = new Blueprint('sqlite');
        $bp->enum('status', ['active', 'inactive']);

        $sql = $bp->toCreateSql('t');

        $this->assertStringContainsString('TEXT', $sql);
        $this->assertStringContainsString("CHECK (`status` IN ('active', 'inactive'))", $sql);
    }

    /**
     * @return void
     */
    public function test_enum_throws_on_empty_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $bp = new Blueprint('mysql');
        $bp->enum('status', []);
    }

    /**
     * @return void
     */
    public function test_enum_throws_on_empty_string_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty strings');

        $bp = new Blueprint('mysql');
        $bp->enum('status', ['active', '']);
    }

    /**
     * @return void
     */
    public function test_enum_throws_on_unsafe_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains unsafe characters');

        $bp = new Blueprint('mysql');
        $bp->enum('status', ["active'; DROP TABLE users--"]);
    }

    /**
     * @return void
     */
    public function test_enum_throws_on_value_with_space(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains unsafe characters');

        $bp = new Blueprint('mysql');
        $bp->enum('status', ['active value']);
    }

    /**
     * @return void
     */
    public function test_enum_allows_hyphen_in_value(): void
    {
        $bp = new Blueprint('mysql');
        $bp->enum('status', ['active', 'in-progress', 'done_2']);

        $this->assertStringContainsString("ENUM('active', 'in-progress', 'done_2')", $bp->toCreateSql('t'));
    }

    // =========================================================================
    // Feature 14: Column Modifiers (after/first/change)
    // =========================================================================

    /**
     * @return void
     */
    public function test_after_modifier_in_alter_sql(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->string('email')->after('name');

        $stmts = $bp->toAlterSql('users');

        $this->assertCount(1, $stmts);
        $this->assertStringContainsString('ADD COLUMN `email` VARCHAR(255) NOT NULL AFTER `name`', $stmts[0]);
    }

    /**
     * @return void
     */
    public function test_first_modifier_in_alter_sql(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->integer('sort_order')->first();

        $stmts = $bp->toAlterSql('users');

        $this->assertCount(1, $stmts);
        $this->assertStringContainsString('ADD COLUMN `sort_order` INTEGER NOT NULL FIRST', $stmts[0]);
    }

    /**
     * @return void
     */
    public function test_change_generates_modify_column(): void
    {
        $bp = new Blueprint('mysql', 'alter');
        $bp->string('name')->change();

        $stmts = $bp->toAlterSql('users');

        $this->assertCount(1, $stmts);
        $this->assertStringContainsString('MODIFY COLUMN `name` VARCHAR(255) NOT NULL', $stmts[0]);
    }

    /**
     * @return void
     */
    public function test_change_skipped_on_sqlite(): void
    {
        $bp = new Blueprint('sqlite', 'alter');
        $bp->string('name')->change();

        $stmts = $bp->toAlterSql('users');

        // SQLite does not support MODIFY COLUMN — should produce no statements
        $this->assertCount(0, $stmts);
    }

    // =========================================================================
    // Security: identifier validation
    // =========================================================================

    /**
     * @return void
     */
    public function test_invalid_table_name_throws_in_create(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        $bp = new Blueprint('mysql');
        $bp->id();
        $bp->toCreateSql('users; DROP TABLE users--');
    }

    /**
     * @return void
     */
    public function test_invalid_index_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        $bp = new Blueprint('mysql');
        $bp->id();
        $bp->index('email', 'bad name!');
        $bp->toIndexSql('users');
    }
}
