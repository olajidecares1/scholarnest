# 1. Keep this application; do not rebuild from scratch

- **Status:** Accepted, 21 August 2026
- **Decided by:** Olajide

## What happened

A fresh Laravel 13 project was started in `C:\xampp\htdocs\AkademicNest`, which was
an empty folder at the time, following the project brief's roadmap ("Stage 1,
Set up the Laravel 13 project").

Configuring its database surfaced the problem. Running the migrations failed
with:

```
SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'schools' already exists
```

The `akademicnest` database already belonged to **this** application, which was
alive and well in `Documents\AkademicNest`.

The empty `htdocs\AkademicNest` folder was misleading: this project used to live
there and had been moved. The `public/storage` symlink still pointed back at the
old location, which is a separate bug that came out of the same discovery (see
below).

## Nothing was damaged

The failed `CREATE TABLE` was rejected by the database, so it wrote nothing.
Verified afterwards:

- no rows added to the `migrations` table
- `akademicnest` still holds 99 tables and all its data
- `Documents\AkademicNest` and `D:\AkademicNest` were never written to

The Laravel 13 experiment was then repointed at a separate database,
`akademicnest_v13`, so it could not collide.

## The choice

| Option | Meaning |
| ------ | ------- |
| A | Keep this app as-is, discard the rebuild |
| B | Rebuild from scratch on Laravel 13, as the brief literally describes |
| C | Keep this app **and** bring it up to the brief's standard |

## Decision: C

The brief reads as a greenfield specification, but it is better understood as a
description of the standard AkademicNest should meet, not an instruction to start
again.

The deciding evidence was the state of this codebase:

- **941 passing tests** with 2,411 assertions
- **Tenant isolation applied consistently**: 45 of 48 controllers that bind a
  school-owned model check ownership, and the 3 that do not are correct to skip
  it (two are Super Admin, one is deliberately public QR verification)
- **Sequential ids already kept out of URLs**, with tests proving it
- **Login throttling already matching the brief** at 5 attempts per 15 minutes
- **Password reset already correct**: 60-minute expiry, single use, hashed
  tokens
- **Uploads already re-encoded through GD**, which strips EXIF and proves the
  file is a real image

That is not a codebase to throw away. Rebuilding would have cost months to
arrive back at features that already work, and would have put 143 students'
records, 2,691 attendance rows and 1,227 examination scores through an
unnecessary migration.

The gaps that do exist, no per-IP rate limiting, no block on script execution
in upload directories, Laravel 12 rather than 13, are all reachable from here,
and are tracked in [docs/SECURITY-AUDIT.md](../../SECURITY-AUDIT.md) and
[TODO.md](../../../TODO.md).

## Consequences

- Work continues in `Documents\AkademicNest`, on the `dev` branch.
- The Laravel 12 → 13 and PHP 8.2 → 8.3 upgrade becomes a planned, staged task
  rather than a starting condition. PHP 8.3.33 is already installed at
  `C:\php83` for that purpose.
- `C:\xampp\htdocs\AkademicNest` holds an abandoned Laravel 13 skeleton and the
  `akademicnest_v13` database. Both can be deleted once confirmed unwanted.
- `D:\AkademicNest` is a stale copy and should be removed once GitHub is set up, so
  there is no chance of editing the wrong one.

## Related fix

The same investigation found that `public/storage` was a junction pointing at
`C:\xampp\htdocs\AkademicNest\storage\app\public` the old location, which is now
an unrelated empty project. Every uploaded student photo, staff photo and school
logo on the site was a broken image.

Re-linked with `php artisan storage:link` on 21 August 2026.
