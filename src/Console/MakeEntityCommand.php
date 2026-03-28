<?php

declare(strict_types=1);

namespace EzPhp\Orm\Console;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeEntityCommand
 *
 * Scaffolds a new Data Mapper entity class in app/Entities/.
 *
 * @package EzPhp\Orm\Console
 */
final readonly class MakeEntityCommand implements CommandInterface
{
    /**
     * @param string $srcPath  Absolute path to the application src/ directory
     */
    public function __construct(private string $srcPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:entity';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new Data Mapper entity class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:entity <ClassName>';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            fwrite(STDERR, "Usage: ez make:entity <ClassName>\n");

            return 1;
        }

        $dir = $this->srcPath . DIRECTORY_SEPARATOR . 'Entities';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Entity already exists: $filename\n");

            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create entity: $filename\n");

            return 1;
        }

        echo "Created: src/Entities/$filename\n";

        return 0;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Entities;

            use EzPhp\\Orm\\Entity;

            final class $name extends Entity
            {
                protected static string \$table = '';

                /** @var list<string> */
                protected static array \$fillable = [];
            }
            PHP;
    }
}
