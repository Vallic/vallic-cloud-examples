<?php

/**
 * Everything that differs between environments, read from the environment.
 *
 * The platform hands every container its settings as environment variables;
 * nothing here is committed. The fallbacks are for a laptop, where the
 * variables do not exist.
 *
 * https://docs.vallic.com/variables
 */

return [
    'db' => [
        // mysql for MariaDB and MySQL, pgsql for PostgreSQL.
        'dsn' => sprintf(
            '%s:host=%s;port=%s;dbname=%s',
            getenv('DB_DRIVER') ?: 'mysql',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_NAME') ?: 'app',
        ),
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],

    // Valkey or Redis, when the stack runs one.
    'redis' => getenv('REDIS_HOST')
        ? ['host' => getenv('REDIS_HOST'), 'port' => (int) getenv('REDIS_PORT')]
        : null,

    // Generated once per environment and never changes: a signing key that
    // survives every deploy and is never in a repository.
    'secret' => getenv('VALLIC_ENTROPY') ?: 'not-a-secret-on-a-laptop',

    // https:// and the primary hostname, for absolute links.
    'base_url' => getenv('PROJECT_BASE_URL') ?: 'http://localhost',

    // production, staging or development.
    'debug' => getenv('VALLIC_ENVIRONMENT_TYPE') !== 'production',

    // Written files that must outlive a release, never served.
    'private_dir' => getenv('VALLIC_PRIVATE_DIR') ?: dirname(__DIR__) . '/private',

    // Log files here are collected with the rest of your logs.
    'log_file' => (getenv('VALLIC_LOG_DIR') ?: dirname(__DIR__) . '/private') . '/app.log',
];
