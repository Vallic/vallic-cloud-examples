# Drupal, dedicated

A large Drupal site on the **Dedicated** shape: several web servers behind a
load balancer, every service on a machine of its own, worker machines for
background work, Vinyl Cache (Varnish) in front, Solr for search, RabbitMQ for
imports, Redis for the cache, a theme built with Node, an extra machine for an
image tool — and configuration overridden per environment.

Handbook: [Shapes](https://docs.vallic.com/shapes) ·
[Drupal](https://docs.vallic.com/framework-drupal) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | MariaDB, Redis, Solr, RabbitMQ, Vinyl Cache and Node for the theme; Composer and theme builds; `drush deploy`; two workers; cron; the `voyager` extra |
| `web/sites/default/settings.php` | The project's own settings, then the two files below, in order |
| `web/sites/default/settings.vallic.php` | The platform's, as the handbook ships it: database, salt, files, hosts, proxy, Redis, mail |
| `web/sites/default/settings.overrides.php` | This project's: config overridden per environment, and Solr, Vinyl Cache, RabbitMQ and the extra wired from the environment |
| `.vallic/commands/sanitization.yml` | Scrubs accounts, re-indexes search and clears caches when production's data is copied into staging or development |
| `.vallic/varnish/recv.vcl`, `deliver.vcl` | A cart and a checkout never cached; Drupal's debugging headers taken off |
| `.vallic/extra/voyager.yml` | What runs on the extra |

## The machines

Infrastructure is bought in the console, not committed. Dedicated puts every
service on a machine of its own, so this manifest expects:

| Machine | For |
|---|---|
| Load balancer | Always there on Dedicated |
| Web servers × 2 or more | The site. Two is the minimum, so the tier survives losing one |
| Database | MariaDB, backed up every four hours |
| Cache | Redis |
| Search | Solr — without it the deploy is refused: *has no search machine to run it on* |
| Broker | RabbitMQ |
| Cache proxy | Vinyl Cache (Varnish), in front of the web servers |
| Worker machines × 2 | The `imports` and `heavy` workers, and cron |
| Extra (`voyager`) | rembg, on the private network |

**Workers need room.** An environment runs one *different* worker per worker
machine, or one per web server where it has none — so the two workers here
need two worker machines (or, without any, two web servers, the minimum).
`replicas` runs copies of one worker within that room. A third worker would be
refused until there is a third machine for it.

With worker machines, cron and every worker move there, and the web servers
only answer requests.

## settings.overrides.php

`settings.vallic.php` stays as the handbook ships it, so a newer copy can
replace it whole. Everything this project adds goes in its own file, included
after it:

- **Per environment** — a `development` config split on everywhere but
  production; CSS and JS aggregation only in production; `stage_file_proxy`
  fetching production's images on staging and development.
- **Solr** — the server's host and login, through `search_api_solr`'s
  basic-auth connector.
- **Vinyl Cache (Varnish)** — the purger's host and port. Replace
  `YOUR_PURGER_ID` with your purger's id.
- **RabbitMQ** — the `rabbitmq` module's connection, and the `acme_import`
  queue routed through it. Every other queue stays in the database.
- **The extra** — its address, assigned when the machine is built, into a
  custom module's configuration.

`$config` overrides apply at runtime and are never exported, so `drush cex`
on staging does not write staging's hosts into `config/sync`.

## Notes

- **The database has to hold a site** before the first deploy: `drush deploy`
  updates a site, it does not install one. See [`../drupal/`](../drupal/).
- **The theme builds in the Node image** (`image: node`), because the PHP image
  has no npm. Node runs nothing beside the site.
- **A copy's search index starts stale.** The sanitisation marks everything for
  re-indexing, and the `search-index` cron works through it.
- **Variables to set in the console:** `REMBG_TOKEN`, for the extra.
- More on each part: [`../varnish/`](../varnish/) for cache rules,
  [`../extras/`](../extras/) for the extra machine.

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

### Redis

| Variable | What it is |
|---|---|
| `REDIS_HOST` | The cache service, whether it is Redis or Valkey — both speak the same protocol and the name is what the Drupal redis module reads. Absent when the stack runs neither. |
| `REDIS_PORT` | Its port, `6379`. |

### Solr

| Variable | What it is |
|---|---|
| `SOLR_HOST` | The Solr service, when the stack runs it. Port 8983. |
| `SOLR_USER` | The user Solr's login accepts, `solr`. Solr refuses a request without it. |
| `SOLR_PASSWORD` | Its password. Derived per environment and never regenerated. Arrives through the secret channel. |

### Vinyl Cache (Varnish)

| Variable | What it is |
|---|---|
| `VARNISH_HOST` | The HTTP cache in front of the application, when the stack runs it — where to send purge requests. A purge sent here needs no key. |
| `VARNISH_PURGE_KEY` | The key a purge needs when it comes in from outside, in an `X-VC-Purge-Key` header. Arrives through the secret channel. |
| `VARNISH_SECRET` | The secret for Varnish's admin interface (`varnishadm`, port 6082), for anything that drives the cache that way rather than by HTTP purge. Derived per environment. Arrives through the secret channel. |

### RabbitMQ

| Variable | What it is |
|---|---|
| `RABBITMQ_HOST` | The RabbitMQ broker, when the stack runs it. |
| `RABBITMQ_PORT` | Its AMQP port, `5672`. |
| `RABBITMQ_USER` | The user to connect as, `app`. The image's `guest` is removed. |
| `RABBITMQ_PASSWORD` | Its password. Derived per environment and never regenerated. Arrives through the secret channel. |
| `RABBITMQ_URL` | The same as one `amqp://` URL, on the default virtual host. Arrives through the secret channel, because it carries the password. |

### Mail

| Variable | What it is |
|---|---|
| `SMTP_HOST` | The mail relay, when the stack runs one. Port 25 from inside the stack; the relay is what talks to the outside world. |

### Extra machine — voyager

| Variable | What it is |
|---|---|
| `EXTRA_VOYAGER_HOST` | The `voyager` extra machine's address on the private network, assigned when it is built. Absent where the environment has no machine for it. |
| `EXTRA_VOYAGER_REMBG_PORT` | The port the rest of the environment reaches `rembg` on — the first number of its `expose` entry. |

The extra's own containers get only the variables named under `extra.voyager.env.required` in `vallic.yaml`, never the ones above. See [Extra machines](https://docs.vallic.com/extras#reaching-it-from-your-application).

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
