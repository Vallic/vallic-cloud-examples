# Drupal

Drupal 10 or 11 in the `drupal/recommended-project` layout — `composer.json`
at the top of the repository, Drupal under `web/`.

Handbook: [Drupal](https://docs.vallic.com/framework-drupal) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | The manifest: PHP, MariaDB and Valkey, the Composer build and its cache, `drush deploy`, cron every fifteen minutes |
| `web/sites/default/settings.vallic.php` | Database, hash salt, files, trusted hosts, reverse proxy, Redis, mail — all from the environment |
| `.vallic/commands/sanitization.yml` | `drush sql:sanitize` when production's data is copied into staging or development |

## settings.vallic.php

The same file the Vallic Cloud console runs on. Include it from
`web/sites/default/settings.php`, **last**, so it wins over the defaults above
it:

```php
if (file_exists($app_root . '/' . $site_path . '/settings.vallic.php')) {
  include $app_root . '/' . $site_path . '/settings.vallic.php';
}
```

Off the platform — DDEV, a laptop, another host — `VALLIC_ENVIRONMENT` is not
set and the file returns before touching anything, so it can be committed and
forgotten.

It leaves a few things to you, because they live in a module's configuration
rather than in `$settings` — add them to the same file as the site needs them:

```php
// Solr, through search_api_solr's basic-auth connector.
if (getenv('SOLR_HOST')) {
  $solr = &$config['search_api.server.solr']['backend_config'];
  $solr['connector'] = 'basic_auth';
  $solr['connector_config']['host'] = getenv('SOLR_HOST');
  $solr['connector_config']['port'] = 8983;
  $solr['connector_config']['core'] = getenv('VALLIC_SLUG');
  $solr['connector_config']['username'] = getenv('SOLR_USER');
  $solr['connector_config']['password'] = getenv('SOLR_PASSWORD');
  unset($solr);
}

// Vinyl Cache (Varnish), for the varnish_purger module.
if (getenv('VARNISH_HOST')) {
  $config['varnish_purger.settings.YOUR_PURGER_ID']['hostname'] = getenv('VARNISH_HOST');
  $config['varnish_purger.settings.YOUR_PURGER_ID']['port'] = 6081;
}
```

With the CDN on, install [Vallic Purge](https://www.drupal.org/project/vallic_purge):
it reads its settings from the environment and needs nothing here. See
[CDN](https://docs.vallic.com/cdn#drupal).

## Notes

- **`drush deploy` runs after the release is live**, never in the build: a
  build has no database. With no `deploy.steps`, a deploy runs no updates and
  imports no configuration.
- **The database has to hold a site.** `drush deploy` updates a site; it does
  not install one, and on an empty database it fails and the deploy rolls
  back. Move an existing site in with `vallic db import` before the first
  deploy. For a new site, deploy once with the `deploy` block left out, run
  `drush site:install` over SSH, then put the block back.
- **Only the files directories are writable.** Public files are
  `web/sites/default/files`; private files are `private/` beside `web/`. Both
  outlive every release.
- **The database under `services` must be the environment's own.** A deploy
  naming another is refused — changing a database is a migration.
- With Varnish in the stack, see [`../varnish/`](../varnish/) for rules of your
  own.

## Environment variables

Written by the platform into every container of this environment — read them, never commit their values. The full list, with what each one is for: [Variables](https://docs.vallic.com/variables).

### Where the site is

| Variable | What it is |
|---|---|
| `VALLIC_ENVIRONMENT` | The environment's name — `production`, `staging`, `pr-42`. |
| `VALLIC_ENVIRONMENT_TYPE` | What kind it is: `production`, `staging`, `development` or `preview`. The value to switch behaviour on — error verbosity, an environment indicator, whether to send real mail. |
| `VALLIC_SLUG` | The environment's slug, `{project}-{environment}`, unique across the platform. |
| `VALLIC_HOSTNAMES` | Every hostname the edge routes to this environment, comma-separated. Trust these Host headers and no others. Absent until something routes here. |
| `PROJECT_BASE_URL` | `https://` and the primary hostname — the one every other hostname redirects to. What to write into absolute links when no request says. Absent until something routes here. |
| `DRUSH_OPTIONS_URI` | Drupal projects only: `PROJECT_BASE_URL` under the name Drush reads, so cron and one-off commands write the same URLs a request would. |

### Secrets

| Variable | What it is |
|---|---|
| `VALLIC_ENTROPY` | 256 random bits, base64-encoded, generated once per environment and never changed. Derive the site's own secrets from it — Drupal's hash salt, WordPress's salts — rather than committing them. Arrives through the secret channel, so it is never in a task payload. |

### Database — MariaDB

| Variable | What it is |
|---|---|
| `DB_HOST` | The database service, reachable by this name from every container. Absent when the stack runs no database. |
| `DB_PORT` | Its port. |
| `DB_DRIVER` | `mysql` for MariaDB, `pgsql` for PostgreSQL. |
| `DB_NAME` | The database, created on the service's first start. |
| `DB_USER` | The application's user — not the superuser. |
| `DB_PASSWORD` | Its password. Generated once with the environment and never regenerated. Arrives through the secret channel. |
| `DB_ROOT_PASSWORD` | The superuser's password, on engines that have one. For a migration or a repair, not for the site. |
| `DATABASE_URL` | The same credentials as one connection string, which is what Doctrine reads and what most Node and Go drivers accept. Arrives through the secret channel, because it carries the password. |

### Valkey

| Variable | What it is |
|---|---|
| `REDIS_HOST` | The cache service, whether it is Redis or Valkey — both speak the same protocol and the name is what the Drupal redis module reads. Absent when the stack runs neither. |
| `REDIS_PORT` | Its port, `6379`. |
| `VALKEY_HOST` | The Valkey service by its own name, when the stack runs Valkey. |

### Mail

| Variable | What it is |
|---|---|
| `SMTP_HOST` | The mail relay, when the stack runs one. Port 25 from inside the stack; the relay is what talks to the outside world. |

### Files

| Variable | What it is |
|---|---|
| `VALLIC_PUBLIC_DIR` | Absolute path, inside the container, of the directory the framework serves uploads from. It outlives every release. |
| `VALLIC_PRIVATE_DIR` | Absolute path of the directory that is never served — Drupal's private files, Laravel's `storage/app`. It outlives every release. |

### Paths

| Variable | What it is |
|---|---|
| `WEB_ROOT` | Where the live release is inside every container: `/var/www/html/current`. The directory above it holds every release kept for rollback. |
| `COMPOSER_ROOT` | The same directory — where `composer.json` is. |
| `DRUPAL_ROOT` | `/var/www/html/current/web`, the document root of a Drupal project. |
| `VALLIC_LOG_DIR` | `/var/log/app`, where an application writes log files it wants collected. Anything written here is shipped with the rest of the environment's logs and kept in the machine's own copy. It outlives every release. |

### CDN — when the project has one

| Variable | What it is |
|---|---|
| `VALLIC_CDN_PURGE_SOCKET` | The Unix socket to send a purge through. Present only when the project has bought a CDN. |
| `VALLIC_CDN_PURGE_URL` | The URL to POST a purge to, through that socket. Only its path matters. |
| `VALLIC_CDN_PURGE_TOKEN` | Says which environment is asking. It is not a CDN credential and cannot reach another environment's cache. Arrives through the secret channel. |
