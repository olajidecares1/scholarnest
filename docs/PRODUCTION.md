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
| `APP_KEY` | a generated key | `php artisan key:generate`. Sessions and every `encrypted` cast depend on it, see the warning under Backups. |
| `APP_URL` | `https://akademicanest.com` | Every link `route()` generates, every link in an email, the host stray traffic is redirected to, and the default target for custom domains. **Enforced**: an http, local or missing value is refused. |
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
akademicanest.com          A      <server IP>     the platform itself
*.akademicanest.com        A      <server IP>     every Standard school
```

**The wildcard needs a wildcard TLS certificate to match** (`*.akademicanest.com`).
Without one, every Standard school's website shows a certificate warning, which
is worse than not offering subdomains at all. Leave `TENANT_BASE_DOMAIN` blank
until the certificate exists; turning it on later is additive and breaks no
existing link.

Exclusive schools point their own domain at the server. What they are told to
create comes from `CUSTOM_DOMAIN_A_RECORD_IP` and `CUSTOM_DOMAIN_CNAME_TARGET`.

### The ownership TXT record, and the old names it still answers to

`CUSTOM_DOMAIN_TXT_PREFIX` is `_akademicnest-verify`. That is the only prefix a
school is ever shown.

A TXT record lives at the school's own registrar, on a domain this platform does
not control, so renaming the prefix alone would silently un-verify every domain
that was verified under an older name. Verification therefore also accepts
`config('custom_domain.legacy_txt_verification_prefixes')` currently
`_edunest-verify` and `_scholarnest-verify` without advertising them. Empty
that list once no verified domain relies on an old prefix.

## Strongly recommended

| Key | Production value | Why |
| --- | --- | --- |
| `SESSION_DRIVER` | `redis` (or `database`) | `file` does not survive more than one web node. |
| `CACHE_STORE` | `redis` | Same reason. |
| `QUEUE_CONNECTION` | `redis` (or `database`) | Used by a worker when one is running. CBT extraction works without one. |
| `MAIL_MAILER` | `smtp` (with `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`) | Welcome emails, invoices, top-up confirmations and password reset codes. Left on `log`, nothing is delivered: the app refuses to call those emails sent and shows the reason. Prove delivery with `php artisan mail:test you@example.com` or **Settings -> Email delivery -> Send test email**. |
| `LOG_LEVEL` | `warning` | `debug` writes request detail to disk indefinitely. |

## A queue worker is optional

CBT extraction does not need one. `App\Services\CbtExtractionRunner` sends a
paper to the queue only when a worker's heartbeat shows one is alive. With no
worker, the web server reads the paper straight after sending the page back,
and the upload page reads anything still waiting when it is opened or polled.

A worker is still worth running on a busy server, so large PDFs are read away
from the web processes. `deploy/supervisor/akademicnest-worker.conf` is the
supervisor program that keeps one up. Install it, then:

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

## On Laravel Cloud, four of those are different

Everything above describes one server you control. Laravel Cloud is not that,
and four of the instructions invert:

| On a server | On Laravel Cloud |
| --- | --- |
| supervisor runs the queue | A **Worker cluster** runs it. Do not add `queue:restart` to the deploy commands, Cloud restarts workers itself after every deploy. |
| `storage:link` publishes uploads | **Do not run it.** The filesystem is ephemeral: it is reset by every deploy, each replica has its own, and the link would not survive. Both disks must be object storage. **The simplest way: attach buckets whose disk names match the app's disks**: a *public* bucket named `public` (school logos, website, gallery, news and CBT question images) and a *private* bucket named `local` (photographs of students, staff and parents, signatures, stamps, receipts, class notes, CBT documents, report evidence), neither marked default. **Until they are attached, uploads are kept in the database** (`App\Support\Storage\DatabaseStorageFallback`): they survive deploys and work on every instance, with public files served at `/files/...`. Buckets remain the better home for large volumes of photos and documents; after attaching them and redeploying, run `php artisan uploads:move-to-buckets` to carry the database copies across. Run `php artisan uploads:check --references` from the Commands tab at any time to see where uploads are going, and which database rows point at files that are already gone. Laravel replaces a disk with the bucket of the same name at boot (`Illuminate\Foundation\Cloud::configureDisks`), public address included, so no variables are needed. A bucket under any other name is ignored by the app. The `PUBLIC_*`/`PRIVATE_*` variables in `.env.production.example` are the manual alternative. The **platform logo and favicon do not depend on any of this**: they are kept in the database and served at `/branding/{file}` (see `App\Models\BrandingImage`). |
| `config:cache` after deploying | Runs in the **build** command, not the deploy command. Deploy commands run on a filesystem that is thrown away. |
| `A` records to a server IP | Laravel Cloud shows the exact origin records to create. There is no server IP to point at, so **`CUSTOM_DOMAIN_A_RECORD_IP` stays empty** and Exclusive schools are given the CNAME target instead. |

Two consequences worth knowing before selling a plan:

- **Every Exclusive school's domain must be added in the Cloud dashboard** for
  Cloud to route it and issue its certificate. Pointing DNS alone is not
  enough. Custom domains are capped per plan (Starter includes 10) and billed
  beyond that.
- **The Standard plan's wildcard needs pre-verification.** A wildcard
  certificate requires the DCV delegation `CNAME` under `_acme-challenge` to
  stay in place permanently, or renewal fails silently months later. Leave
  `TENANT_BASE_DOMAIN` blank until the wildcard is verified.

## Backups

Two things, and losing either alone loses the data:

- **The database.**
- **`APP_KEY`.** Result tokens, session payloads and every `encrypted` model
  attribute are unreadable without it. A database restored without the key it
  was written under is a database of ciphertext.

`storage/app` holds uploaded media, logos, receipts, ID card photos, CBT
source documents, and is not in the database. Back it up too.

## What is deliberately not here

Secrets. This file records which keys must be set and why; the values belong in
the server's `.env`, which is git-ignored along with `.env.backup*` and
`storage/backups`.
