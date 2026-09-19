<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Logging\LoggerInterface;
use EzPhp\Logging\LogLevel;
use EzPhp\Orm\LoggingDatabase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Spy logger recording every log() call. Named with an Orm prefix — the
 * shared Tests\ namespace means a plain SpyLogger would collide with
 * ez-php/logging's own test fixture in the aggregated root suite.
 */
final class OrmLoggingDatabaseSpyLogger implements LoggerInterface
{
    /** @var list<array{level: LogLevel, message: string, context: array<string, mixed>}> */
    public array $entries = [];

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
}

#[CoversClass(LoggingDatabase::class)]
final class LoggingDatabaseTest extends TestCase
{
    private PdoDatabase $inner;

    private OrmLoggingDatabaseSpyLogger $logger;

    protected function setUp(): void
    {
        $this->inner = new PdoDatabase('sqlite::memory:');
        $this->inner->query('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $this->inner->query("INSERT INTO items (name) VALUES ('Widget')");
        $this->logger = new OrmLoggingDatabaseSpyLogger();
    }

    public function testQueryDelegatesToInnerDatabase(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger);

        $rows = $db->query('SELECT * FROM items');

        self::assertCount(1, $rows);
        self::assertSame('Widget', $rows[0]['name']);
    }

    public function testQueryLogsSqlBindingsAndDuration(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger);

        $db->query('SELECT * FROM items WHERE name = ?', ['Widget']);

        self::assertCount(1, $this->logger->entries);
        $entry = $this->logger->entries[0];
        self::assertSame('SELECT * FROM items WHERE name = ?', $entry['message']);
        self::assertSame(['Widget'], $entry['context']['bindings']);
        self::assertArrayHasKey('duration_ms', $entry['context']);
        self::assertIsFloat($entry['context']['duration_ms']);
    }

    public function testExecuteDelegatesAndLogs(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger);

        $affected = $db->execute("INSERT INTO items (name) VALUES ('Gadget')");

        self::assertSame(1, $affected);
        self::assertCount(1, $this->logger->entries);
        self::assertSame("INSERT INTO items (name) VALUES ('Gadget')", $this->logger->entries[0]['message']);
    }

    public function testDefaultLogLevelIsDebug(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger);

        $db->query('SELECT * FROM items');

        self::assertSame(LogLevel::DEBUG, $this->logger->entries[0]['level']);
    }

    public function testCustomLogLevelIsUsed(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger, LogLevel::INFO);

        $db->query('SELECT * FROM items');

        self::assertSame(LogLevel::INFO, $this->logger->entries[0]['level']);
    }

    public function testGetPdoDelegatesToInnerDatabase(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger);

        self::assertSame($this->inner->getPdo(), $db->getPdo());
    }

    public function testTransactionDelegatesToInnerDatabaseWithoutLogging(): void
    {
        $db = new LoggingDatabase($this->inner, $this->logger);

        $result = $db->transaction(fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertSame([], $this->logger->entries);
    }
}
