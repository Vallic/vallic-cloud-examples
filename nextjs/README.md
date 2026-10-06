# Next.js

A Next.js application served by `next start`.

Handbook: [Node.js](https://docs.vallic.com/stack-nodejs) ·
[Node and Go](https://docs.vallic.com/framework-node) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | The manifest: Node and PostgreSQL, the build with `.next/cache` kept between builds, `start` and `port`, a migration on deploy, a health check |
| `package.json` | Next.js and `pg`; `build`, `start`, `migrate` and `sanitize` scripts |
| `next.config.mjs` | Compression off — the proxy does it |
| `app/healthz/route.js` | The health check the deploy waits for — it asks the database |
| `scripts/sanitize.mjs` | Scrubs the tables that hold people |
| `.vallic/commands/sanitization.yml` | Runs it when production's data is copied into staging or development |

`scripts/migrate.mjs`, named in `package.json`, stands for your own migration
tool.

## Notes

- **`start` and `port` are the defaults** and can be left out: `npm start` is
  `next start`, which listens on `PORT`. The platform sets `HOSTNAME` to
  `0.0.0.0`, which Next.js binds, and `NODE_ENV` to `production`.
- **The build has no environment.** The same artifact is deployed to staging
  and production, so nothing environment-specific may be baked in at build
  time — not through `next.config`'s `env`, and not through `NEXT_PUBLIC_`
  variables, which are inlined into the client bundle when it is built. Read
  `process.env` in server code at request time; the environment's own address
  is `PROJECT_BASE_URL`.
- **Pages that need data are dynamic.** A page prerendered at build time
  cannot query the database, because the build has none. Mark such routes
  `dynamic = 'force-dynamic'`, or revalidate them.
- **The release is read-only.** Write what you keep under
  `VALLIC_PRIVATE_DIR` or a declared mount.
- **With more than one web server**, Next.js's own cache is per process. Each
  machine fills its own; that is correct, only less efficient.

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
