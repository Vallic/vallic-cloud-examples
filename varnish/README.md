# Vinyl Cache (Varnish)

Rules of your own for the Varnish cache, beside any stack that runs it.
Varnish caches whole responses in front of your site — nginx for a PHP
application, the application itself for Node.js or Go — so a hit never reaches
it.

Handbook: [Vinyl Cache (Varnish)](https://docs.vallic.com/stack-vinyl) ·
[vallic.yaml](https://docs.vallic.com/configuration#services)

## Files

| File | Added to | The example |
|---|---|---|
| `vallic.yaml` | — | A Drupal manifest with Vinyl Cache (Varnish) under `services`, with a five-minute default lifetime |
| `.vallic/varnish/recv.vcl` | `vcl_recv` | Never cache a cart, a checkout or an account page, or a preview; read a currency cookie into a header |
| `.vallic/varnish/hash.vcl` | `vcl_hash` | One cached copy per currency |
| `.vallic/varnish/backend-response.vcl` | `vcl_backend_response` | A feed kept an hour; errors kept seconds |
| `.vallic/varnish/deliver.vcl` | `vcl_deliver` | `X-Powered-By` and `X-Generator` taken off; a default `Referrer-Policy` |

Copy only the files you need. A file you leave out adds nothing.

## The rules

- **They add to the platform's policy, never replace it.** Each file holds a
  block for its one subroutine — `sub vcl_recv { … }` in `recv.vcl` — and
  runs before the platform's own handling of that step.
- **Not allowed:** a `backend`, `vcl_init`, `import` or `include`. Where
  requests go and what runs inside the cache are the platform's.
- **No `return` in `hash.vcl` or `deliver.vcl`.** The platform adds the URL
  and the host to the cache key after `hash.vcl`; a `return` would leave them
  out and serve every page from one object.
- **At most 8 KB a file.** A file that breaks a rule is left out and the
  policy runs without it; a policy that does not compile is not loaded, and
  the last one that worked keeps running.
- **Read from the commit of the release you deployed.** A change takes effect
  with the deploy that carries it, and a rollback brings back the rules of the
  release you roll back to.

On another stack, take the `vinyl` entry under `services` into your own
manifest.

## Purging from your application

The cache is at `VARNISH_HOST`, port 6081. A purge from inside the stack needs
no key; one arriving from outside needs `VARNISH_PURGE_KEY` in an
`X-VC-Purge-Key` header. For Drupal's purger, see [`../drupal/`](../drupal/).

From your own computer, `vallic tunnel <environment> varnish` opens it on
`http://127.0.0.1:6081`.

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

### Vinyl Cache (Varnish)

| Variable | What it is |
|---|---|
| `VARNISH_HOST` | The HTTP cache in front of the application, when the stack runs it — where to send purge requests. A purge sent here needs no key. |
| `VARNISH_PURGE_KEY` | The key a purge needs when it comes in from outside, in an `X-VC-Purge-Key` header. Arrives through the secret channel. |
| `VARNISH_SECRET` | The secret for Varnish's admin interface (`varnishadm`, port 6082), for anything that drives the cache that way rather than by HTTP purge. Derived per environment. Arrives through the secret channel. |

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
