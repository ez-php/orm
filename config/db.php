<?php

declare(strict_types=1);

return [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'database',
    'username' => getenv('DB_USERNAME') ?: 'username',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'testing_database' => getenv('DB_TESTING_DATABASE') ?: 'ez-php_testing',
];
