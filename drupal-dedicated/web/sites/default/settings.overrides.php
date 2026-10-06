<?php

/**
 * Configuration overridden per environment, and the services' wiring.
 *
 * settings.vallic.php covers what every Drupal site needs. What is left lives
 * in a module's own configuration rather than in $settings, so a site places
 * it — here, from the variables the platform writes:
 *
 * https://docs.vallic.com/framework-drupal#what-it-leaves-to-you
 * https://docs.vallic.com/variables
 *
 * $config overrides are applied at runtime and never exported: `drush cex`
 * writes the values in the database, not these, so staging's Solr host never
 * ends up in config/sync.
 */

// Off the platform this file changes nothing, like settings.vallic.php.
if (getenv('VALLIC_ENVIRONMENT') === FALSE) {
  return;
}

$vallic_type = getenv('VALLIC_ENVIRONMENT_TYPE') ?: 'development';
$vallic_production = $vallic_type === 'production';

// ---------------------------------------------------------------------------
// Per environment.
// ---------------------------------------------------------------------------

// Development modules and settings live in a config split that is on
// everywhere but production. `drush deploy` imports with it in effect.
$config['config_split.config_split.development']['status'] = !$vallic_production;

// Aggregated CSS and JS in production; readable files everywhere else.
$config['system.performance']['css']['preprocess'] = $vallic_production;
$config['system.performance']['js']['preprocess'] = $vallic_production;

// Staging and development show production's images without copying every
// file: stage_file_proxy fetches a missing one from production on first use.
if (!$vallic_production) {
  $config['stage_file_proxy.settings']['origin'] = 'https://www.example.com';
  $config['stage_file_proxy.settings']['hotlink'] = FALSE;
}

// ---------------------------------------------------------------------------
// Solr, through search_api_solr's basic-auth connector. Solr refuses a request
// without the login.
// ---------------------------------------------------------------------------
if (getenv('SOLR_HOST')) {
  $solr = &$config['search_api.server.solr']['backend_config'];
  $solr['connector'] = 'basic_auth';
  $solr['connector_config']['host'] = getenv('SOLR_HOST');
  $solr['connector_config']['port'] = 8983;
  $solr['connector_config']['core'] = getenv('VALLIC_SLUG');
  $solr['connector_config']['username'] = getenv('SOLR_USER');
  $solr['connector_config']['password'] = getenv('SOLR_PASSWORD');
  unset($solr);

  // Only production's index answers searches with production's data; a copy
  // indexes into its own core, named by its own slug, above.
}

// ---------------------------------------------------------------------------
// Vinyl Cache (Varnish), for the varnish_purger module. A purge sent from the
// site to VARNISH_HOST needs no key.
// ---------------------------------------------------------------------------
if (getenv('VARNISH_HOST')) {
  $config['varnish_purger.settings.YOUR_PURGER_ID']['hostname'] = getenv('VARNISH_HOST');
  $config['varnish_purger.settings.YOUR_PURGER_ID']['port'] = 6081;
}

// ---------------------------------------------------------------------------
// RabbitMQ, for the rabbitmq module. The `imports` worker in vallic.yaml runs
// `drush rabbitmq:worker acme_import` against this connection.
// ---------------------------------------------------------------------------
if (getenv('RABBITMQ_HOST')) {
  $settings['rabbitmq_credentials']['default'] = [
    'host' => getenv('RABBITMQ_HOST'),
    'port' => (int) (getenv('RABBITMQ_PORT') ?: 5672),
    'vhost' => '/',
    'username' => getenv('RABBITMQ_USER'),
    'password' => getenv('RABBITMQ_PASSWORD'),
  ];
  // The import queue goes through the broker; every other queue stays in the
  // database, where the `heavy` worker and cron run it.
  $settings['queue_service_acme_import'] = 'queue.rabbitmq.default';
}

// ---------------------------------------------------------------------------
// The extra machine. Its address is assigned when the machine is built, so it
// arrives in the environment rather than being written down. A custom module
// reads the endpoint from its own configuration.
// ---------------------------------------------------------------------------
if (getenv('EXTRA_VOYAGER_HOST')) {
  $config['acme_media.settings']['rembg_endpoint'] = sprintf(
    'http://%s:%s',
    getenv('EXTRA_VOYAGER_HOST'),
    getenv('EXTRA_VOYAGER_REMBG_PORT'),
  );
}
