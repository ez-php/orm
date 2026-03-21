<?php

declare(strict_types=1);

namespace EzPhp\Orm\Console;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeModelCommand
 *
 * @package EzPhp\Orm\Console
 */
final readonly class MakeModelCommand implements CommandInterface
{
    /**
     * MakeModelCommand Constructor
     *
     * @param string $srcPath
     */
    public function __construct(private string $srcPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:model';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new ORM model class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:model <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:model <ClassName>\n");
            return 1;
        }

        $dir = $this->srcPath . DIRECTORY_SEPARATOR . 'Models';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Model already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create model: $filename\n");
            return 1;
        }

        echo "Created: src/Models/$filename\n";

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

            namespace App\\Models;

            use EzPhp\\Orm\\Model;

            final class $name extends Model
            {
                protected static string \$table = '';

                /** @var list<string> */
                protected static array \$fillable = [];
            }
            PHP;
    }
}
