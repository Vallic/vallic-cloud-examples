# Laravel

Laravel 11 or 12. The document root is `public/`.

Handbook: [Laravel](https://docs.vallic.com/framework-laravel) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | The manifest: PHP, MariaDB and Valkey, Composer and Vite builds, migrations and caches on deploy, a queue worker, the scheduler every minute |
| `bootstrap/app.php` | The platform's variables where `env()` sees them on the command line, compiled config somewhere writable, the edge trusted as a proxy |
| `app/Console/Commands/Sanitize.php` | `php artisan app:sanitize` — the tables that hold people, scrubbed |
| `.vallic/commands/sanitization.yml` | Runs it when production's data is copied into staging or development |

The platform writes the database and Redis settings under the names Laravel
already reads — `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `REDIS_PORT`. **Do not commit a
`.env`**: it would apply to every environment the code reaches.

## Changes to config/

**`config/app.php`** — the key, and the environment's own address:

```php
// Set APP_KEY as a secret variable in the console, or derive it from the
// environment's entropy, which is generated once and never changes. If you
// derive it, take APP_KEY out of env.required in vallic.yaml.
'key' => env('APP_KEY') ?: (getenv('VALLIC_ENTROPY')
    ? 'base64:' . base64_encode(substr(hash('sha256', getenv('VALLIC_ENTROPY'), true), 0, 32))
    : null),

'url' => env('APP_URL', env('PROJECT_BASE_URL', 'http://localhost')),
```

**`config/filesystems.php`** — `public/storage` is already linked to the
public files directory, so point the `public` disk there:

```php
'public' => [
    'driver' => 'local',
    'root' => env('VALLIC_PUBLIC_DIR', storage_path('app/public')),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
```

**`config/logging.php`** — logs where they are collected:

```php
'daily' => [
    'driver' => 'daily',
    'path' => env('VALLIC_LOG_DIR', '/var/log/app') . '/laravel.log',
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 7,
],
```

## Variables to set in the console

| Variable | Value |
|---|---|
| `APP_KEY` | Unless derived from `VALLIC_ENTROPY`, above |
| `APP_ENV` | `production` |
| `LOG_CHANNEL` | `daily` |
| `CACHE_STORE`, `SESSION_DRIVER` | `redis` — required once there is more than one web server |

## Notes

- **Migrations run after the release is live**, in `deploy.steps`, never in the
  build: a build has no database.
- **The release is read-only.** `storage/app`, `storage/logs` and
  `storage/framework` are linked out of it and outlive every release;
  `public/storage` is linked to the public files directory, so there is no
  `storage:link` to run.
- **Never commit a cached config.** It freezes whatever the environment said
  when it was built — on this platform, the wrong environment's.
- **`trustProxies(at: '*')`** in `bootstrap/app.php` is because the edge is the
  only thing a request arrives from and terminates TLS before it; without it
  Laravel builds `http://` URLs.

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
| `DB_CONNECTION` | The same value as `DB_DRIVER`, under the name Laravel reads. |
| `DB_DATABASE` | The same value as `DB_NAME`, under the name Laravel reads. |
| `DB_USERNAME` | The same value as `DB_USER`, under the name Laravel reads. |

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
| `VALLIC_LOG_DIR` | `/var/log/app`, where an application writes log files it wants collected. Anything written here is shipped with the rest of the environment's logs and kept in the machine's own copy. It outlives every release. |

### CDN — when the project has one

| Variable | What it is |
|---|---|
| `VALLIC_CDN_PURGE_SOCKET` | The Unix socket to send a purge through. Present only when the project has bought a CDN. |
| `VALLIC_CDN_PURGE_URL` | The URL to POST a purge to, through that socket. Only its path matters. |
| `VALLIC_CDN_PURGE_TOKEN` | Says which environment is asking. It is not a CDN credential and cannot reach another environment's cache. Arrives through the secret channel. |
