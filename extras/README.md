# Extras

A machine of your own beside the site, running containers you name in a
compose file — here a background remover and an OCR engine next to a Drupal
site. The same works beside any stack.

Handbook: [Extra machines](https://docs.vallic.com/extras)

## Files

| File | What it does |
|---|---|
| `vallic.yaml` | A Drupal manifest with an `extra` block: what the site may reach on `voyager`, the variables its containers need, internet access |
| `.vallic/extra/voyager.yml` | What runs on `voyager` — an ordinary compose file |
| `.vallic/extra/voyager/rembg/` | Your own files for the `rembg` container, mounted read-only at `/app` |

The images in `voyager.yml` are placeholders; name your own, with a version.

## Two files

`vallic.yaml` is the platform's: every key is checked. `.vallic/extra/<slot>.yml`
is yours. The slot — `voyager` here — is the name your environment's extra was
given, in the order `pioneer`, `voyager`, `galileo`, `magellan`, `cassini`,
`juno`.

## Reaching it from your application

The address is assigned when the machine is built, so it arrives in your
application's environment:

```
EXTRA_VOYAGER_HOST=10.16.0.7
EXTRA_VOYAGER_REMBG_PORT=8001
EXTRA_VOYAGER_EASYOCR_PORT=8002
```

From PHP, for example:

```php
$rembg = sprintf('http://%s:%s', getenv('EXTRA_VOYAGER_HOST'), getenv('EXTRA_VOYAGER_REMBG_PORT'));
```

Nothing on an extra is reachable from the internet or through your site's
domain. Design for it being unavailable: a request that hangs waiting on the
tool is worse for a visitor than a page without what the tool would have added.

## Volumes

The first word of a volume says what it is, and there is no default:

| Prefix | What it is |
|---|---|
| `keep/` | Data you would be sorry to lose. Backed up |
| `cache/` | Anything the container can fetch or rebuild — model weights, scratch files. Never backed up |
| `code/` | Your files from `.vallic/extra/<slot>/`, out of the release you deployed. Read-only |

## Notes

- **Each environment has its own extra.** Staging reads the same
  `vallic.yaml`, but production's extra is not staging's: give staging one on
  the Resources tab. Until it has one, staging deploys without the tool; a
  production deploy without its extra is refused.
- **Published images only** — `build:` is not supported. Build in CI, push to a
  registry, name the tag. A private registry's login goes on the project's
  Configuration page, under Other → Registry logins.
- **Credentials by name** under `extra.<slot>.env.required`, values in the
  console — never in the compose file, which is in your repository.
- **Memory is divided** between the services in the file; CPU is shared. Size
  the machine for their total.
- **Not covered by the uptime SLA** — an image we did not write. Support does
  cover it.

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
| `SMTP_HOST` | The mail relay. Port 25 from inside the stack, unauthenticated; the relay is what talks to the outside world, through the provider set as its `RELAY_HOST` on port 587 — direct delivery on port 25 is blocked by most cloud providers. |

The relay's provider is the `opensmtpd` entry under `services` in `vallic.yaml`; set its password as a secret variable, `RELAY_PASSWORD`, in the console. See [OpenSMTPD](https://docs.vallic.com/stack-opensmtpd).

### Extra machine — voyager

| Variable | What it is |
|---|---|
| `EXTRA_VOYAGER_HOST` | The `voyager` extra machine's address on the private network, assigned when it is built. Absent where the environment has no machine for it. |
| `EXTRA_VOYAGER_REMBG_PORT` | The port the rest of the environment reaches `rembg` on — the first number of its `expose` entry. |
| `EXTRA_VOYAGER_EASYOCR_PORT` | The port the rest of the environment reaches `easyocr` on — the first number of its `expose` entry. |

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
