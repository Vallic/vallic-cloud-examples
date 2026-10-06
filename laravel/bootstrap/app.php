<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// The platform's variables, where env() can see them. Laravel reads $_ENV and
// $_SERVER, and PHP's command line leaves both empty of the environment — so
// without this a deploy step or a cron job sees no DB_HOST at all, and
// `artisan migrate` quietly falls back to Laravel's defaults.
foreach (getenv() as $name => $value) {
    $_ENV[$name] ??= $value;
    $_SERVER[$name] ??= $value;
}

// Compiled config and routes somewhere writable. The release is mounted
// read-only, so bootstrap/cache cannot be written and `config:cache` fails.
// Per release, so a rollback does not run the configuration of the release
// it rolled back from.
$compiled = (getenv('VALLIC_PRIVATE_DIR') ?: dirname(__DIR__) . '/private')
    . '/bootstrap/' . basename(dirname(__DIR__));
if (!is_dir($compiled)) {
    @mkdir($compiled, 0775, true);
}
foreach ([
    'APP_CONFIG_CACHE' => 'config.php',
    'APP_ROUTES_CACHE' => 'routes.php',
    'APP_EVENTS_CACHE' => 'events.php',
    'APP_SERVICES_CACHE' => 'services.php',
] as $variable => $file) {
    $_ENV[$variable] = $_SERVER[$variable] = $compiled . '/' . $file;
    putenv($variable . '=' . $compiled . '/' . $file);
}

// Laravel's own bootstrap from here on, unchanged.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The edge terminates TLS and forwards over plain HTTP, and is the only
        // thing a request ever arrives from — so trust it for the scheme, host
        // and client address it forwards.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
