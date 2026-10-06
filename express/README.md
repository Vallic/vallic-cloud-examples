# Express

An Express 5 application serving its own HTTP, with sessions in Valkey.

Handbook: [Node and Go](https://docs.vallic.com/framework-node) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | The manifest: Node, PostgreSQL and Valkey, the npm build, `start` and `port`, a migration on deploy, a health check, `SESSION_SECRET` required |
| `package.json` | Express, `express-session` with `connect-redis`, `pg`; `start`, `migrate` and `sanitize` scripts |
| `src/server.js` | The proxy trusted, sessions in Valkey, a health check that asks the database, `PORT` on every interface |
| `src/sanitize.js` | Scrubs the tables that hold people |
| `.vallic/commands/sanitization.yml` | Runs it when production's data is copied into staging or development |

`src/migrate.js`, named in `package.json`, stands for your own migration tool.

## Notes

- **`app.set('trust proxy', 1)`** — the platform's proxy terminates TLS in
  front of the process. Without it `req.protocol` is `http`, `req.ip` is the
  proxy, and a `secure` session cookie is never set.
- **Sessions in Valkey.** `express-session`'s default store is the process's
  memory: lost on every deploy, and missing on the next request whenever the
  environment has more than one web server.
- **Set `SESSION_SECRET`** as a secret variable in the console. `vallic.yaml`
  lists it under `env.required`, so a deploy without it is refused rather than
  starting with a guessable secret.
- **Bind `PORT` on `0.0.0.0`**, and write what you keep under
  `VALLIC_PRIVATE_DIR` or a declared mount — the release is read-only.

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

### Runtime

| Variable | What it is |
|---|---|
| `PORT` | What to listen on, on every interface — `3000` unless `port` in `vallic.yaml` says otherwise. Setting `port` moves this and the port the platform reaches together. |
| `HOSTNAME` | `0.0.0.0`, which Next.js and others bind. |
| `NODE_ENV` | `production`. |

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
| `SMTP_HOST` | The mail relay. Port 25 from inside the stack, unauthenticated; the relay is what talks to the outside world, through the provider set as its `RELAY_HOST` on port 587 — direct delivery on port 25 is blocked by most cloud providers. |

The relay's provider is the `opensmtpd` entry under `services` in `vallic.yaml`; set its password as a secret variable, `RELAY_PASSWORD`, in the console. See [OpenSMTPD](https://docs.vallic.com/stack-opensmtpd).

### Files

| Variable | What it is |
|---|---|
| `VALLIC_PRIVATE_DIR` | Absolute path of the directory that is never served — Drupal's private files, Laravel's `storage/app`. It outlives every release. |

### Paths

| Variable | What it is |
|---|---|
| `WEB_ROOT` | Where the live release is inside every container: `/var/www/html/current`. The directory above it holds every release kept for rollback. |
| `VALLIC_LOG_DIR` | `/var/log/app`, where an application writes log files it wants collected. Anything written here is shipped with the rest of the environment's logs and kept in the machine's own copy. It outlives every release. |

### CDN — when the project has one

| Variable | What it is |
|---|---|
| `VALLIC_CDN_PURGE_SOCKET` | The Unix socket to send a purge through. Present only when the project has bought a CDN. |
| `VALLIC_CDN_PURGE_URL` | The URL to POST a purge to, through that socket. Only its path matters. |
| `VALLIC_CDN_PURGE_TOKEN` | Says which environment is asking. It is not a CDN credential and cannot reach another environment's cache. Arrives through the secret channel. |
