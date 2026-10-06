<?php

/**
 * What the deploy waits for (health.path in vallic.yaml).
 *
 * Asks the database rather than answering because PHP is up: a check that
 * passes while the site cannot reach its data is a green tick that means
 * nothing. Anything from 200 to 399 is healthy.
 */

$config = require dirname(__DIR__) . '/src/config.php';

try {
    $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $pdo->query('SELECT 1');
}
catch (Throwable) {
    http_response_code(503);
    echo "database unreachable\n";

    return;
}

header('Cache-Control: no-store');
echo "ok\n";
