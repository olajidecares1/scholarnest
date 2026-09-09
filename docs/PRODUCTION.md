# Production configuration

Closes finding 5 of [SECURITY-AUDIT.md](SECURITY-AUDIT.md).

The values below are the ones that differ between a laptop and a server. The
defaults in `.env.example` are development defaults, and three of them are
actively dangerous in production. `App\Support\ProductionConfiguration` refuses
to serve HTTP requests when any of those three is wrong, so a deployment that
gets them wrong fails loudly at the first request instead of quietly leaking.

Console commands are deliberately exempt from that check: a bad value baked
into `config:cache` would otherwise stop `php artisan config:clear` from
running, locking you out of the fix.

## Required

| Key | Production value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | Turns on the check below, and forces https URL generation. |
| `APP_DEBUG` | `false` | Debug pages print stack traces containing database credentials to whoever triggered the error. **Enforced.** |
| `APP_KEY` | a generated key | `php artisan key:generate`. Sessions and every `encrypted` cast depend on it — see the warning under Backups. |
| `APP_URL` | `https://akademicnest.com` | Every link `route()` generates, every link in an email, the host stray traffic is redirected to, and the default target for custom domains. **Enforced** — an http, local or missing value is refused. |
| `SESSION_SECURE_COOKIE` | `true` | Without it the cookie that *is* the session is sent over plain http. **Enforced.** |
| `SESSION_ENCRYPT` | `true` | Session payloads are otherwise readable wherever they are stored. **Enforced.** |

## Addresses, per plan

Run **`php artisan production:urls`** after editing `.env`. It prints every
address this deployment will serve, per tier, with worked examples from real
schools in the database, and exits non-zero if any of them will not work.

Each tier fails differently, which is why only the first is enforced at boot:

| Tier | Setting | If it is missing |
| --- | --- | --- |
| **Platform** | `APP_URL` | Every generated link goes nowhere. **Refused at boot.** |
| **Basic** | `BASIC_PORTAL_TOKEN` | The portal 404s, and a Basic school has *no other way in*. Generate with `php artisan basic-portal:token`. |
| **Standard** | `TENANT_BASE_DOMAIN` | Subdomains silently fall back to `/p/{key}` paths. Works, but is not what the plan sells. |
| **Exclusive** | `CUSTOM_DOMAIN_A_RECORD_IP` | The setup wizard cannot tell a school which A record to create. |

A Basic misconfiguration takes one tier down. Refusing every request over it
would take the other two down as well, which is why the command reports these
rather than the boot check refusing them.

### What the DNS has to look like

```
akademicnest.com          A      <server IP>     the platform itself
*.akademicnest.com        A      <server IP>     every Standard school
```

**The wildcard needs a wildcard TLS certificate to match** (`*.akademicnest.com`).
Without one, every Standard school's website shows a certificate warning — which
is worse than not offering subdomains at all. Leave `TENANT_BASE_DOMAIN` blank
until the certificate exists; turning it on later is additive and breaks no
existing link.

Exclusive schools point their own domain at the server. What they are told to
create comes from `CUSTOM_DOMAIN_A_RECORD_IP` and `CUSTOM_DOMAIN_CNAME_TARGET`.

### One value that must not be renamed

`CUSTOM_DOMAIN_TXT_PREFIX` is still `_edunest-verify` after the rename to
AkademicNest, deliberately. Schools have already created that TXT record at their
own registrar. Changing it does not rename anything — it un-verifies every live
custom domain until each school notices and edits its own DNS. Change it only on
a deployment that has no verified domains yet.

## Strongly recommended

| Key | Production value | Why |
| --- | --- | --- |
| `SESSION_DRIVER` | `redis` (or `database`) | `file` does not survive more than one web node. |
| `CACHE_STORE` | `redis` | Same reason. |
| `QUEUE_CONNECTION` | `redis` (or `database`) | Must not be `sync`: CBT extraction would then run inside the web request. |
| `MAIL_MAILER` | a real transport | Password resets, result notices and credential handovers all go by mail. |
| `LOG_LEVEL` | `warning` | `debug` writes request detail to disk indefinitely. |

## Optional

| Key | Notes |
| --- | --- |
| `ANTHROPIC_API_KEY` | Only payment-receipt screening uses it, and it fails open — absent means receipts are simply not pre-screened. CBT extraction does **not** use it; that runs locally. |

## A queue worker must be running

CBT extraction is queued. With no worker, uploads sit at "Pending" forever —
`App\Services\QueueWorkerHealth` detects this and says so on the page, but
detecting it is not the same as fixing it.

`deploy/supervisor/akademicnest-worker.conf` is the supervisor program that keeps
one up. Install it, then:

```
supervisorctl reread && supervisorctl update && supervisorctl start akademicnest-worker:*
```

Restart the workers on every deploy (`php artisan queue:restart`), or they go
on running the code they were started with.

## After deploying

```
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
npm run build
php artisan storage:link
```

`storage:link` matters more than it looks: uploaded media is served through
`public/storage`, and a link pointing at a path from another machine is how
every image on the site ends up broken.

## Backups

Two things, and losing either alone loses the data:

- **The database.**
- **`APP_KEY`.** Result tokens, session payloads and every `encrypted` model
  attribute are unreadable without it. A database restored without the key it
  was written under is a database of ciphertext.

`storage/app` holds uploaded media — logos, receipts, ID card photos, CBT
source documents — and is not in the database. Back it up too.

## What is deliberately not here

Secrets. This file records which keys must be set and why; the values belong in
the server's `.env`, which is git-ignored along with `.env.backup*` and
`storage/backups`.
