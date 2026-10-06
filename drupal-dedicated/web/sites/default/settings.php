<?php

/**
 * Site settings.
 *
 * Only what is the project's own and the same everywhere lives here. Two files
 * follow it, in this order:
 *
 * - settings.vallic.php — the platform's: database, hash salt, files, trusted
 *   hosts, reverse proxy, Redis, mail, all from the environment. Kept as the
 *   handbook ships it, so it can be replaced by a newer copy whole.
 * - settings.overrides.php — this project's: configuration overridden per
 *   environment, and the wiring for Solr, Vinyl Cache (Varnish), RabbitMQ and
 *   the extra, read from the same environment.
 *
 * Off the platform both return before touching anything, so DDEV and laptops
 * go on using settings.local.php.
 *
 * https://docs.vallic.com/framework-drupal#the-settings-file
 */

$databases = [];

// A project convention rather than a platform fact.
$settings['config_sync_directory'] = '../config/sync';

$settings['update_free_access'] = FALSE;
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

// Last, so the platform's values win over everything above.
if (file_exists($app_root . '/' . $site_path . '/settings.vallic.php')) {
  include $app_root . '/' . $site_path . '/settings.vallic.php';
}
if (file_exists($app_root . '/' . $site_path . '/settings.overrides.php')) {
  include $app_root . '/' . $site_path . '/settings.overrides.php';
}
