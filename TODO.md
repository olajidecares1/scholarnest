# ScholarNest roadmap

What is built, what is not, and what to do next. Updated 26 August 2026.

The working rule for this project:

> **Build → Test → Find problems → Fix → Retest → Confirm → Move on.**
> One thing at a time. Nothing is built ahead of the stage it belongs to.

---

## Where the project stands

A working Laravel 12 application on PHP 8.2, with the whole functional brief
built and tested. The security audit is closed. What remains is platform work:
getting the code off this laptop, the framework upgrade, and the clients.

---

## Built and working

### Portals
- [x] Super Admin — schools, subscriptions, CMS, media, support tickets
- [x] School Admin — full dashboard with dark mode
- [x] Staff portal — registers, score entry, assignments, CBT authoring, diary
- [x] Student portal — results, assignments, attendance, timetable, CBT
- [x] Guardian portal — children's results, attendance, fees, messages

### School management
- [x] Students, staff and guardians, with issued login details and ID sequences
- [x] Academic structure: levels, terms, classes, subjects, offerings
- [x] Teacher assignments and the teacher diary
- [x] Attendance recording
- [x] Examinations, one shared score-entry grid, grade bands with overlap
      refusal and gap reporting
- [x] Report cards, with PDF and Word export
- [x] Result tokens, bound to one student and one examination
- [x] ID card templates, issuing and public QR verification
- [x] Fees: structures, invoices, payments, and results withheld against a
      balance until the school releases them
- [x] Library, transport, hostels, co-curricular activities
- [x] Timetables
- [x] Memoranda addressed to a chosen audience

### CBT
- [x] Question papers read from `.docx` and `.pdf` **locally** — no API key,
      no credit, no network
- [x] The paper's rubric and per-question marks carried across
- [x] Upload progress, and a warning when no queue worker is running
- [x] Student sitting: server-anchored clock, answers that survive a dropped
      connection

### Platform
- [x] Three plans, gated by one `plan_feature` middleware
- [x] Subscription wizard with student capacity as its own step
- [x] Payment-receipt screening that only ever rejects, never approves
- [x] Basic-plan student licences, Super Admin decides the allocation
- [x] Basic-plan portal: token-gated school finder at `/{token}`
- [x] Public school websites, custom domains, news, events, gallery
- [x] Misconduct reporting with media uploads
- [x] Audit logging, maintenance mode
- [x] **API v1** — Sanctum tokens, versioned routes, Eloquent resources,
      per-token rate limiting. See [docs/API.md](docs/API.md)
- [x] **CI** — Pint, `composer audit` and the suite on every push

### Security
- [x] The audit is **closed — all seven findings**. See
      [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md)
- [x] Tenant isolation through one `AuthorizesSchoolOwnership` check
- [x] UUIDs in URLs, never the database's integer keys
- [x] Login throttling per account and per IP; password-reset rate limiting
- [x] Password reset: 60-minute expiry, single use, hashed tokens, and only a
      School Admin may reset Staff, Student and Guardian passwords
- [x] Uploads named from their content against an allowlist; re-encoded through
      GD; EXIF stripped; script execution blocked in upload directories
- [x] Production refuses to serve with `APP_DEBUG` on or an insecure session
      cookie
- [x] Idle session timeout across all portals
- [x] Super Admin sign-in behind a hidden dialog, with nothing on the server
      trusting that it is hidden
- [x] Dependency lock clean under `composer audit`

---

## Next up

### 1. Get the code onto GitHub — *do this first, it is still not done*

The repository is **local only**. Every commit in this project exists on one
disk. [docs/GITHUB.md](docs/GITHUB.md) has the exact commands.

- [ ] Create a private GitHub repository
- [ ] `git remote add origin …`, then push `main` and `dev`
- [ ] Turn on branch protection for `main` and require the CI check
- [ ] Push the parked `upgrade/laravel-13` branch too

*(`.env` has never been committed — verified. `.env.backup*` and
`storage/backups` are ignored.)*

### 2. Make PHP 8.3 the default, then merge Laravel 13

The work is done and waiting on branch **`upgrade/laravel-13`**: composer.json
requires `php ^8.3` and `laravel/framework ^13.0`, and the lock resolves to
Laravel 13.29 with Tinker 3 and Pest 4.

It is not merged because the `php` on PATH is **8.2.12**, and installing that
vendor tree breaks `php artisan serve` with a platform check failure. In order:

- [ ] Install PHP 8.3 as the default (8.3.33 is already at `C:\php83`)
- [ ] `git switch upgrade/laravel-13 && composer install`
- [ ] Run the suite and work through the failures — Pest 3 → 4 is a major jump
      and has not been run yet
- [ ] Update `.github/workflows/ci.yml` to 8.3 in the same commit
- [ ] Merge into `dev` only when the whole suite passes

### 3. Finish the API

v1 is read-only and covers the student and guardian surface. Before the Flutter
work:

- [ ] Staff endpoints: classes, registers, score entry
- [ ] Writes — and the offline/idempotency design that has to come first
- [ ] A password-change endpoint, so an account told to change its password is
      not sent back to the browser
- [ ] Decide whether School Admin gets tokens at all

### 4. The clients

- [ ] PWA: manifest, service worker, offline shell, install prompt
- [ ] Flutter iOS and Android against the API

### 5. Infrastructure

- [ ] Production deployment and HTTPS — [docs/PRODUCTION.md](docs/PRODUCTION.md)
      is the checklist
- [ ] Redis for cache, sessions and queues
- [ ] Backups, and **back up `APP_KEY` with the database** — a database
      restored without it is a database of ciphertext
- [ ] Error tracking
- [ ] CDN for uploaded media

---

## Still worth auditing

The audit was a first pass. It never looked at:

- [ ] The four portal sign-in paths themselves
- [ ] `PortalSessionBroker` and `ValidateSchoolPortalToken`
- [ ] Misconduct-report video upload handling
- [ ] The local document parsers — they now read untrusted `.docx` and `.pdf`
      in-process, which deserves its own pass
- [ ] CSRF coverage on the portal routes
- [ ] Whether `EnsureHasPermission` can be bypassed by direct route access
- [ ] `ReportController::create` lists every school on a public page, which
      publishes the full customer list
- [ ] The ~20 remaining "these two records belong to the same school as each
      other" checks, which are a different rule from the one
      `AuthorizesSchoolOwnership` now holds

---

## Known local issues

- [ ] `C:\xampp\htdocs\ScholarNest` holds an abandoned Laravel 13 skeleton from a
      false start, on the separate `scholarnest_v13` database. Safe to delete once
      you are sure nothing there is wanted. See
      [docs/architecture/decisions/0001-two-codebases.md](docs/architecture/decisions/0001-two-codebases.md).
- [ ] `D:\ScholarNest` is a stale copy from 21 August. Once GitHub is set up it
      becomes redundant and should be removed, so nobody edits the wrong one.
