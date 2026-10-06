# Symfony

A Symfony application is a **PHP** project on Vallic Cloud — the same plain
PHP image, served from `public/`.

Handbook: [Symfony](https://docs.vallic.com/framework-symfony) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | The manifest: PHP, PostgreSQL and Valkey, Composer and Encore builds, Doctrine migrations and the cache on deploy, a Messenger worker, an uploads mount |
| `src/Kernel.php` | Cache and logs in `VALLIC_PRIVATE_DIR`, since the release is read-only |
| `config/packages/framework.yaml` | The secret from `VALLIC_ENTROPY`, the edge as a trusted proxy, sessions in Valkey |
| `.vallic/commands/sanitization.yml` | Scrubs the tables that hold people when production's data is copied into staging or development |

## Notes

- **`DATABASE_URL` is set for you** — the one value Doctrine reads. Keep
  committing `.env` as Symfony expects, but leave `DATABASE_URL` out of it: on
  the command line, where deploy steps run, a committed value is not guaranteed
  to lose to the real one.
- **Set `APP_ENV=prod`** — in the committed `.env`, or per environment in the
  console. The platform does not set it, and Symfony's default is `dev`.
- **Logs you want collected** go to `VALLIC_LOG_DIR`: point Monolog's file
  handler at `%env(VALLIC_LOG_DIR)%/symfony.log`.
- **Nothing is scheduled for you.** Run Symfony Scheduler's transport as a
  worker, or declare `cron` jobs in `vallic.yaml`.
- **`dbal:run-sql`** in the sanitisation file needs DoctrineBundle, which a
  Doctrine project already has.

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

### Secrets

| Variable | What it is |
|---|---|
| `VALLIC_ENTROPY` | 256 random bits, base64-encoded, generated once per environment and never changed. Derive the site's own secrets from it — Drupal's hash salt, WordPress's salts — rather than committing them. Arrives through the secret channel, so it is never in a task payload. |

### Database — PostgreSQL

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
| `VALLIC_PRIVATE_DIR` | Absolute path of the directory that is never served — Drupal's private files, Laravel's `storage/app`. It outlives every release. |

### Paths

| Variable | What it is |
|---|---|
| `WEB_ROOT` | Where the live release is inside every container: `/var/www/html/current`. The directory above it holds every release kept for rollback. |
| `COMPOSER_ROOT` | The same directory — where `composer.json` is. |
| `VALLIC_LOG_DIR` | `/var/log/app`, where an application writes log files it wants collected. Anything written here is shipped with the rest of the environment's logs and kept in the machine's own copy. It outlives every release. |

### CDN — when the project has one

| Variable | What it is |
|---|---|
| `VALLIC_CDN_PURGE_SOCKET` | The Unix socket to send a purge through. Present only when the project has bought a CDN. |
| `VALLIC_CDN_PURGE_URL` | The URL to POST a purge to, through that socket. Only its path matters. |
| `VALLIC_CDN_PURGE_TOKEN` | Says which environment is asking. It is not a CDN credential and cannot reach another environment's cache. Arrives through the secret channel. |
