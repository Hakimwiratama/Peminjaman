<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('SIPINTAR_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('SIPINTAR_DB_PORT') ?: '3306',
        'name' => getenv('SIPINTAR_DB_NAME') ?: 'sipintar',
        'user' => getenv('SIPINTAR_DB_USER') ?: 'root',
        'password' => getenv('SIPINTAR_DB_PASSWORD') ?: '',
    ],
];
