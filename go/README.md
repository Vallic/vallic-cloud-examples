# Go

A Go binary that serves its own HTTP.

Handbook: [Node and Go](https://docs.vallic.com/framework-node) ·
[Go](https://docs.vallic.com/stack-golang) ·
[vallic.yaml](https://docs.vallic.com/configuration)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | The manifest: Go and PostgreSQL, two binaries built, `start` (required for Go) and `port`, a health check |
| `go.mod`, `go.sum` | The module, with `pgx` for PostgreSQL |
| `cmd/server/main.go` | A server on `PORT`, every interface, a health check that asks the database, a clean shutdown |
| `cmd/sanitize/main.go` | Scrubs the tables that hold people |
| `.vallic/commands/sanitization.yml` | Runs `bin/sanitize` when production's data is copied into staging or development |

Commit `go.sum` with your own module — run `go mod tidy` after changing
dependencies. The build verifies modules against it.

## Notes

- **`start` is required for Go.** The image is the upstream Go image with no
  default worth running.
- **The release is the binary.** Nothing to prune and usually nothing to run
  at deploy time. If your schema needs migrating, a `deploy` step running your
  own migration tool is the place for it.
- **Bind `PORT` on every interface** (`:8080`, not `127.0.0.1:8080`).
- **The release is read-only.** Write what you keep under
  `VALLIC_PRIVATE_DIR` or a declared mount.
- **A binary that does not serve** — a queue consumer, a stream reader — is
  better written as a `worker` beside a site than as `start`: a worker has no
  port and no hostname to answer 502 on.
- **Nothing is scheduled for you**, and a restore clears no cache.

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
| `PORT` | What to listen on, on every interface — `8080` unless `port` in `vallic.yaml` says otherwise. Setting `port` moves this and the port the platform reaches together. |

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
