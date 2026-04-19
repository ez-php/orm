<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Orm\Console\MakeRepositoryCommand;
use Tests\TestCase;

/**
 * Class MakeRepositoryCommandTest
 *
 * @package Tests\Console
 */
final class MakeRepositoryCommandTest extends TestCase
{
    private string $srcPath;

    protected function setUp(): void
    {
        $this->srcPath = sys_get_temp_dir() . '/ez-php-make-repo-' . uniqid();
        mkdir($this->srcPath);
    }

    protected function tearDown(): void
    {
        $dir = $this->srcPath . '/Repositories';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        rmdir($this->srcPath);
    }

    public function testNameAndDescription(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        self::assertSame('make:repository', $command->getName());
        self::assertNotEmpty($command->getDescription());
    }

    public function testCreatesRepositoryFile(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['User']);
        ob_get_clean();

        self::assertSame(0, $code);
        self::assertFileExists($this->srcPath . '/Repositories/UserRepository.php');
    }

    public function testCreatesRepositoriesDirectoryIfNotExists(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        self::assertDirectoryDoesNotExist($this->srcPath . '/Repositories');

        ob_start();
        $command->handle(['Post']);
        ob_get_clean();

        self::assertDirectoryExists($this->srcPath . '/Repositories');
    }

    public function testGeneratedFileContainsRepositoryAndEntityNames(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $command->handle(['Order']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Repositories/OrderRepository.php');
        self::assertIsString($content);
        self::assertStringContainsString('class OrderRepository', $content);
        self::assertStringContainsString('extends AbstractRepository', $content);
        self::assertStringContainsString('@extends AbstractRepository<Order>', $content);
    }

    public function testGeneratedFileContainsEntityClassMethod(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $command->handle(['Product']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Repositories/ProductRepository.php');
        self::assertIsString($content);
        self::assertStringContainsString('Product::class', $content);
        self::assertStringContainsString('entityClass()', $content);
    }

    public function testGeneratedFileContainsNamespacesAndImports(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $command->handle(['Tag']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Repositories/TagRepository.php');
        self::assertIsString($content);
        self::assertStringContainsString('namespace App\\Repositories', $content);
        self::assertStringContainsString('use App\\Entities\\Tag', $content);
        self::assertStringContainsString('use EzPhp\\Orm\\AbstractRepository', $content);
    }

    public function testPrintsCreatedMessage(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['Invoice']);
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Created:', $output);
        self::assertStringContainsString('InvoiceRepository.php', $output);
    }

    public function testReturns1WithoutNameArgument(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        self::assertSame(1, $code);
    }

    public function testReturns1ForInvalidClassName(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['123Invalid']);
        ob_get_clean();

        self::assertSame(1, $code);
    }

    public function testGeneratedClassIsNotFinal(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $command->handle(['Widget']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Repositories/WidgetRepository.php');
        self::assertIsString($content);
        self::assertStringNotContainsString('final class', $content);
        self::assertStringContainsString('class WidgetRepository', $content);
    }

    public function testReturns1IfRepositoryAlreadyExists(): void
    {
        $command = new MakeRepositoryCommand($this->srcPath);

        ob_start();
        $command->handle(['Category']);
        $code = $command->handle(['Category']);
        ob_get_clean();

        self::assertSame(1, $code);
    }
}
