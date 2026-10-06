<?php

/**
 * WordPress on Vallic Cloud: everything that differs between environments is
 * read from the environment the platform writes, nothing is committed.
 *
 * https://docs.vallic.com/framework-wordpress
 * https://docs.vallic.com/variables
 */

// The database. DB_NAME, DB_USER, DB_PASSWORD and DB_HOST are WordPress's own
// constant names, and the platform writes variables under exactly those names.
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_HOST', getenv('DB_HOST') . ':' . (getenv('DB_PORT') ?: '3306'));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'wp_';

// Salts, derived from the environment's own entropy rather than committed.
// One value, eight distinct keys: they differ from each other and from every
// other environment, and rotating the entropy rotates all eight.
$vallic_entropy = getenv('VALLIC_ENTROPY') ?: '';
foreach ([
  'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
  'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
] as $vallic_salt) {
  define($vallic_salt, hash_hmac('sha256', $vallic_salt, $vallic_entropy));
}

// The edge terminates TLS and proxies over plain HTTP. Without this WordPress
// writes http:// into every absolute URL and redirects in a loop.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
  $_SERVER['HTTPS'] = 'on';
}

// The environment's own address: https:// and its primary hostname.
if (getenv('PROJECT_BASE_URL')) {
  define('WP_HOME', getenv('PROJECT_BASE_URL'));
  define('WP_SITEURL', getenv('PROJECT_BASE_URL'));
}

// Debugging everywhere but production, and errors to the log, never to a
// visitor. VALLIC_LOG_DIR is collected with the rest of your logs, and
// wp-content is read-only.
define('WP_DEBUG', getenv('VALLIC_ENVIRONMENT_TYPE') !== 'production');
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', (getenv('VALLIC_LOG_DIR') ?: '/var/log/app') . '/wordpress.log');

// The release is read-only: plugins, themes and updates arrive by deploy, not
// from wp-admin. These turn the buttons off rather than leaving them to fail.
define('DISALLOW_FILE_MODS', true);
define('AUTOMATIC_UPDATER_DISABLED', true);

// vallic.yaml runs `wp cron event run --due-now` on a schedule instead.
define('DISABLE_WP_CRON', true);

// The object cache in Valkey, for the Redis Object Cache plugin — worth it
// once there is more than one web server, so every one sees the same values.
if (getenv('REDIS_HOST')) {
  define('WP_REDIS_HOST', getenv('REDIS_HOST'));
  define('WP_REDIS_PORT', (int) getenv('REDIS_PORT'));
  define('WP_REDIS_PREFIX', getenv('VALLIC_SLUG'));
}

if (!defined('ABSPATH')) {
  define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
