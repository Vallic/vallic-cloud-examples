# Vallic Cloud examples

Working configuration for running an application on
[Vallic Cloud](https://vallic.com/cloud), one folder per stack. Copy the files
from the folder that matches your project into the root of your repository and
adjust what is yours — the service versions, the build steps, the commands.

Everything here follows the handbook, which is the reference:
**[docs.vallic.com](https://docs.vallic.com/)**.

## Stacks

| Folder | Application | Handbook |
|---|---|---|
| [`drupal/`](drupal/) | Drupal 10 or 11, `drupal/recommended-project` layout | [Drupal](https://docs.vallic.com/framework-drupal) |
| [`drupal-dedicated/`](drupal-dedicated/) | A large Drupal site on Dedicated: several web servers, workers, Solr, Redis, RabbitMQ, Vinyl Cache (Varnish), an extra, a Node theme build, config overridden per environment | [Shapes](https://docs.vallic.com/shapes) · [Drupal](https://docs.vallic.com/framework-drupal) |
| [`wordpress/`](wordpress/) | WordPress, classic layout | [WordPress](https://docs.vallic.com/framework-wordpress) |
| [`laravel/`](laravel/) | Laravel 11 or 12 | [Laravel](https://docs.vallic.com/framework-laravel) |
| [`php/`](php/) | Any other PHP application, and [Symfony](php/symfony/) | [PHP-FPM](https://docs.vallic.com/stack-php) · [Symfony](https://docs.vallic.com/framework-symfony) |
| [`nodejs/`](nodejs/) | Any Node.js application that serves its own HTTP | [Node and Go](https://docs.vallic.com/framework-node) |
| [`nextjs/`](nextjs/) | Next.js | [Node.js](https://docs.vallic.com/stack-nodejs) |
| [`express/`](express/) | Express | [Node and Go](https://docs.vallic.com/framework-node) |
| [`go/`](go/) | A Go binary that serves its own HTTP | [Node and Go](https://docs.vallic.com/framework-node) |

## Beside a stack

| Folder | What it shows | Handbook |
|---|---|---|
| [`varnish/`](varnish/) | Rules of your own for Vinyl Cache (Varnish) — `.vallic/varnish/*.vcl` | [Vinyl Cache (Varnish)](https://docs.vallic.com/stack-vinyl) |
| [`extras/`](extras/) | A machine of your own beside the site, running containers from a compose file | [Extra machines](https://docs.vallic.com/extras) |

Every stack folder also has a `.vallic/commands/sanitization.yml`: what to
scrub from a database copied into staging or development. See
[Sanitising what arrives](https://docs.vallic.com/backup-storage#sanitising-what-arrives).

## What lives where

```
vallic.yaml                         the manifest — required, at the root
.vallic/commands/sanitization.yml   scrubbing after a copy or restore, never on production
.vallic/varnish/*.vcl               additions to the cache policy, when the stack runs Varnish
.vallic/extra/<slot>.yml            what runs on an extra machine
.vallic/extra/<slot>/               the files those containers mount
```

**Application facts go in the repository; infrastructure is chosen in the
console.** Machine sizes, regions and the database are bought, not committed.
A `services` entry starts a cache or a search engine on the next deploy, but
the database it names has to be the one the environment was created with. See
[vallic.yaml](https://docs.vallic.com/configuration).

**Mail leaves through a provider.** Every stack runs an OpenSMTPD relay your
application sends to on port 25, inside the stack. Every example names a
provider for it under `services` — SendGrid, over STARTTLS on 587 — because
straight delivery on port 25 is blocked by most cloud providers, and without a
relay host mail never leaves. Put your provider there, and its password in the
console as `RELAY_PASSWORD`. See [OpenSMTPD](https://docs.vallic.com/stack-opensmtpd).

**Credentials never go in the repository.** Every example reads them from the
environment the platform writes — the full list is in
[Variables](https://docs.vallic.com/variables). A variable your application
cannot start without goes under `env.required` in `vallic.yaml`, and its value
in the console.

## Versions

The versions written here are the ones the handbook's own examples use. Name
the version you depend on, not a build of it: `php: '8.4'`, `mariadb: '11.8'`.
The platform matches it to the current build, so a security rebuild reaches you
without a commit. What is on offer is listed in
[Software stacks](https://docs.vallic.com/stacks).
