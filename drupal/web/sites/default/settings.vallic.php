<?php

/**
 * @file
 * Settings for a Drupal site running on Vallic Cloud.
 *
 * Copy this file next to settings.php and include it from there, after
 * anything it should be allowed to override:
 *
 * @code
 * if (file_exists($app_root . '/' . $site_path . '/settings.vallic.php')) {
 *   include $app_root . '/' . $site_path . '/settings.vallic.php';
 * }
 * @endcode
 *
 * Everything it sets comes from the environment the platform writes for the
 * container — the names are documented in the handbook under "Variables". The
 * platform never edits this file: it is yours, committed with the project,
 * and what it reads is the contract. Anywhere else — DDEV, a laptop, another
 * host — none of those names are set, and the file does nothing.
 */

use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\HttpFoundation\Request;

// Only on the platform. Without this name nothing below is present either.
if (getenv('VALLIC_ENVIRONMENT') === FALSE) {
  return;
}

$settings['vallic_environment'] = getenv('VALLIC_ENVIRONMENT');
$settings['vallic_environment_type'] = getenv('VALLIC_ENVIRONMENT_TYPE') ?: 'development';

// Database. The service is reachable by DB_HOST from every container in the
// stack; the credentials were generated once with the environment.
if (getenv('DB_HOST')) {
  $driver = getenv('DB_DRIVER') ?: 'mysql';
  $databases['default']['default'] = [
    'driver' => $driver,
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME'),
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'prefix' => '',
  ];
  if ($driver === 'mysql') {
    // READ COMMITTED is what Drupal recommends for MariaDB and MySQL; it
    // avoids the gap-lock deadlocks the default isolation level produces
    // under concurrent cache writes.
    $databases['default']['default']['init_commands'] = [
      'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
    ];
  }
  unset($driver);
}

// The hash salt. VALLIC_ENTROPY is 256 random bits generated once for this
// environment and never changed, so sessions and one-time links survive every
// deploy — and nothing secret is committed.
if (getenv('VALLIC_ENTROPY')) {
  $settings['hash_salt'] = getenv('VALLIC_ENTROPY');
}

// Files. Both directories are mounted into every release, so uploads outlive
// the code that received them. Drupal wants the public path relative to the
// document root, so it is derived from the absolute one the platform gives.
if (getenv('VALLIC_PUBLIC_DIR') && str_starts_with(getenv('VALLIC_PUBLIC_DIR'), $app_root . '/')) {
  $settings['file_public_path'] = substr(getenv('VALLIC_PUBLIC_DIR'), strlen($app_root) + 1);
}
if (getenv('VALLIC_PRIVATE_DIR')) {
  $settings['file_private_path'] = getenv('VALLIC_PRIVATE_DIR');
}

// Trusted hosts: exactly the hostnames the edge routes here. Nothing else can
// reach the container, but Drupal builds absolute URLs from the Host header
// and should not take anyone's word for it.
if (getenv('VALLIC_HOSTNAMES')) {
  $settings['trusted_host_patterns'] = array_map(
    static fn(string $hostname): string => '^' . preg_quote(trim($hostname), '/') . '$',
    explode(',', getenv('VALLIC_HOSTNAMES')),
  );
}

// The edge terminates TLS and proxies to the container, so the request Drupal
// sees comes from the proxy over plain HTTP. Trusting the address the request
// arrived from — which is only ever the edge — restores the client's address,
// scheme and port from the X-Forwarded headers the edge sets.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['REMOTE_ADDR'];
$settings['reverse_proxy_trusted_headers'] = Request::HEADER_X_FORWARDED_FOR
  | Request::HEADER_X_FORWARDED_HOST
  | Request::HEADER_X_FORWARDED_PORT
  | Request::HEADER_X_FORWARDED_PROTO;

// Cache in Redis (or Valkey — same protocol, same name) when the stack runs
// one and the site ships the module. The module does not have to be installed:
// its services are registered here and its classes made loadable, so the
// container cache itself lives in Redis from the first request — and so does
// the installer's, which is why the block steps aside during installation.
if (getenv('REDIS_HOST')
  && extension_loaded('redis')
  && file_exists($app_root . '/modules/contrib/redis/redis.services.yml')
  && !InstallerKernel::installationAttempted()) {
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('REDIS_HOST');
  $settings['redis.connection']['port'] = (int) (getenv('REDIS_PORT') ?: 6379);
  // Every environment on a server shares nothing, but a prefix costs nothing
  // and keeps two sites apart should they ever share a service.
  $settings['cache_prefix'] = getenv('VALLIC_SLUG') ?: 'drupal';
  // Every bin not set otherwise. Core already puts bootstrap, config,
  // discovery and routes on its chained backend — APCu in the process, Redis
  // behind it — and a bin's own default is read before this one, so those
  // need no line of their own; naming one is how a bin is taken off it.
  $settings['cache']['default'] = 'cache.backend.redis';
  // Lock, flood and cache-tag checksum in Redis too; then the module's own
  // services, so the backends exist before the module is enabled.
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';
  $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');
  // The container cache is read before the container exists, so it needs
  // its own definition of the services that reach Redis.
  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'redis.factory' => [
        'class' => 'Drupal\redis\ClientFactory',
      ],
      'cache.backend.redis' => [
        'class' => 'Drupal\redis\Cache\CacheBackendFactory',
        'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
      ],
      'cache.container' => [
        'class' => '\Drupal\redis\Cache\PhpRedis',
        'factory' => ['@cache.backend.redis', 'get'],
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
        'arguments' => ['@redis.factory'],
      ],
      'serialization.phpserialize' => [
        'class' => 'Drupal\Component\Serialization\PhpSerialize',
      ],
    ],
  ];
  // And the container itself chained the same way, which core cannot do for
  // it: the definition is a megabyte or so, read on every request, and from
  // APCu that is a local read where from Redis it is a fetch and an
  // unserialise. Redis stays authoritative, so a `drush cr` — a separate
  // process with an APCu of its own — still reaches the web server. Only
  // where APCu is on; the CLI usually has it off.
  if (function_exists('apcu_enabled') && apcu_enabled()) {
    $settings['bootstrap_container_definition']['services'] += [
      'cache.container.consistent' => $settings['bootstrap_container_definition']['services']['cache.container'],
      'cache.container.fast' => [
        'class' => 'Drupal\Core\Cache\ApcuBackend',
        'arguments' => ['container', $settings['cache_prefix'], '@cache_tags_provider.container', '@datetime.time'],
      ],
      'datetime.time' => [
        'class' => 'Drupal\Component\Datetime\Time',
      ],
    ];
    $settings['bootstrap_container_definition']['services']['cache.container'] = [
      'class' => 'Drupal\Core\Cache\ChainedFastBackend',
      'arguments' => ['@cache.container.consistent', '@cache.container.fast', 'container'],
    ];
  }
}

// What differs by kind of environment. Production hides errors from visitors;
// everything else shows them to the people who are there to find them.
switch ($settings['vallic_environment_type']) {
  case 'production':
    $config['system.logging']['error_level'] = 'hide';
    $config['environment_indicator.indicator'] = [
      'name' => 'Production',
      'bg_color' => '#8b0000',
      'fg_color' => '#ffffff',
    ];
    break;

  case 'staging':
    $config['system.logging']['error_level'] = 'some';
    $config['environment_indicator.indicator'] = [
      'name' => 'Staging',
      'bg_color' => '#b86e00',
      'fg_color' => '#ffffff',
    ];
    break;

  default:
    $config['system.logging']['error_level'] = 'verbose';
    $config['environment_indicator.indicator'] = [
      'name' => ucfirst($settings['vallic_environment_type']) . ': ' . $settings['vallic_environment'],
      'bg_color' => '#005f87',
      'fg_color' => '#ffffff',
    ];
    break;
}

// Outbound mail through the stack's relay, when it runs one.
//
// Drupal sends through PHP's mail() by default, which hands the message to a
// sendmail binary the application container does not have — so a site with a
// relay sitting beside it still could not send a password reset until somebody
// found out why. The relay is what talks to the outside world; the application
// only has to be told where it is.
//
// Written as configuration rather than as a module setting, so it applies
// whether the site uses Symfony Mailer 1.x or 2.x, and is simply ignored by a
// site that uses neither. A project sending through a hosted provider sets its
// own key and overrides this in its own settings.
if (getenv('SMTP_HOST')) {
  foreach (['symfony_mailer', 'mailer_transport'] as $prefix) {
    $config[$prefix . '.settings']['default_transport'] = 'vallic';
    $config[$prefix . '.mailer_transport.vallic']['plugin'] = 'smtp';
    $config[$prefix . '.mailer_transport.vallic']['configuration'] = [
      'user' => '',
      'pass' => '',
      'host' => getenv('SMTP_HOST'),
      // 25, stated rather than read: the relay listens on it inside the stack
      // and the platform publishes no variable to change it. Reading one would
      // suggest it is configurable and quietly fall back the day somebody set
      // it expecting that to matter.
      'port' => '25',
    ];
  }
}
