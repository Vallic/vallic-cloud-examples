<?php

/**
 * Scrubs people out of a database that arrived from somewhere else.
 *
 * Called from .vallic/commands/sanitization.yml, which the platform runs after
 * a copy or a restore into staging or development — never on production.
 *
 * https://docs.vallic.com/backup-storage#sanitising-what-arrives
 */

if (getenv('VALLIC_ENVIRONMENT_TYPE') === 'production') {
    fwrite(STDERR, "Refusing to sanitise production.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/src/config.php';
$pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Your own tables that hold people. A failing statement exits non-zero, which
// stops the rest and fails the task — better than half-scrubbed data that
// looks handled.
// The table and column names are an example; use your own.
$pdo->exec("UPDATE users SET email = CONCAT('user', id, '@example.test')");
$pdo->exec('DELETE FROM password_resets');

echo "Sanitised.\n";
