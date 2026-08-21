# EduNest roadmap

What is built, what is not, and what to do next. Updated 21 August 2026.

The working rule for this project:

> **Build → Test → Find problems → Fix → Retest → Confirm → Move on.**
> One thing at a time. Nothing is built ahead of the stage it belongs to.

---

## Where the project stands

EduNest is a working Laravel 12 application with **941 passing tests**. Most of
the functional brief is already built. What remains is mostly hardening,
platform work, and the mobile/API layer.

---

## Built and working

### Portals
- [x] Super Admin — schools, subscriptions, CMS, media, support tickets
- [x] School Admin — full dashboard with dark mode
- [x] Staff portal — registers, score entry, assignments, CBT authoring
- [x] Student portal — results, assignments, attendance, timetable, CBT
- [x] Guardian portal — children's results, attendance, fees, messages

### School management
- [x] Students, staff and guardians
- [x] Academic structure: levels, terms, classes, subjects, offerings
- [x] Teacher assignments
- [x] Attendance recording
- [x] Examinations, scores, grade bands
- [x] Report cards, with PDF and Word export
- [x] Result-checking PINs
- [x] ID card templates, issuing and public QR verification
- [x] Fees: structures, invoices, payments
- [x] Library, transport, hostels, co-curricular activities
- [x] Timetables

### Platform
- [x] Three subscription plans with per-plan feature gating
- [x] Subscription wizard, top-ups and student slot limits
- [x] Public school websites with a block-based builder
- [x] Custom domains with verification
- [x] News, events, gallery, testimonials, facilities, job postings
- [x] Misconduct reporting with media uploads
- [x] Audit logging of sign-ins, failures and password resets
- [x] Maintenance mode

### Security
- [x] Tenant isolation, applied consistently and tested
- [x] UUIDs in URLs instead of sequential ids
- [x] Login throttling — 5 failures / 15 min per account
- [x] Login throttling — 30 failures / 15 min per IP *(added 21 Aug)*
- [x] Password reset rate limiting *(added 21 Aug)*
- [x] Password reset: 60-minute expiry, single use, hashed tokens
- [x] Uploads re-encoded through GD, EXIF stripped
- [x] Script execution blocked in upload directories *(added 21 Aug)*
- [x] Idle session timeout across all portals
- [x] Password reset authority: only a School Admin may reset Staff, Student
      and Guardian passwords, enforced server-side and audited *(added 21 Aug)*

---

## Next up

### 1. Finish the security audit — *do this first*

From [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md). Findings 1, 2 and 4 are
done. Remaining:

- [ ] **Finding 5** — document production configuration, and refuse to boot with
      `APP_DEBUG=true` when `APP_ENV=production`
- [ ] **Finding 3** — derive upload file extensions from content (`$file->extension()`)
      rather than the client-supplied name, at all 14 upload sites
- [ ] **Finding 6** — move the repeated `abort_unless($model->school_id === ...)`
      check into a shared trait or Policy. Gradual, module by module.
- [ ] **Finding 7** — split `routes/web.php` (78 KB) by area

Not yet audited, and worth a second pass:

- [ ] The four portal login flows (Student, Staff, Guardian, School Portal).
      Their password-reset restrictions are now audited and enforced - see
      docs/PASSWORD-RESET-POLICY.md - but the sign-in paths themselves are not.
- [ ] `PortalSessionBroker` and `ValidateSchoolPortalToken`
- [ ] Misconduct-report video upload handling
- [ ] CBT `.docx` import parsing
- [ ] `ReportController::create` lists every school on a public page, which
      publishes the full customer list. Consider limiting it to schools whose
      plan includes misconduct reporting.

### 2. Get the code onto GitHub

The repository is local only. Four weeks of work exists on one machine.

- [ ] Create a private GitHub repository
- [ ] Add it as a remote and push `main` and `dev`
- [ ] Confirm `.env` is absent from the pushed history *(verified locally: it
      has never been committed)*

See [docs/GITHUB.md](docs/GITHUB.md) for the exact commands.

### 3. Upgrade to Laravel 13 and PHP 8.3

The brief asks for Laravel 13 on PHP 8.3+. The app currently runs Laravel 12 on
PHP 8.2.

Do it in this order, testing after each step:

- [ ] Run the suite on PHP 8.3 first, without changing Laravel *(PHP 8.3.33 is
      already installed at `C:\php83`)*
- [ ] Read the Laravel 13 upgrade guide and note the breaking changes
- [ ] Bump `laravel/framework` to `^13.0` on a branch off `dev`
- [ ] Update first-party packages (Breeze, Pest, Pint, Boost)
- [ ] Run the full suite and work through failures
- [ ] Merge only when all 941 tests pass

### 4. Continuous integration

- [ ] GitHub Actions workflow: install dependencies, run Pint, run the suite
- [ ] Require it to pass before merging into `main`

---

## Not built yet

### API layer
Needed before any mobile work. There is no `routes/api.php`.

- [ ] Decide on authentication (Sanctum tokens)
- [ ] Versioned routes (`/api/v1/...`)
- [ ] Eloquent API Resources for every exposed model
- [ ] Tenant resolution for API requests
- [ ] Rate limiting per token

### PWA
- [ ] Web app manifest
- [ ] Service worker and offline shell
- [ ] Install prompt

### Flutter apps
- [ ] iOS and Android clients against the API above

### Infrastructure
- [ ] Production deployment, HTTPS, backups
- [ ] Redis for cache, sessions and queues
- [ ] Error tracking
- [ ] CDN for uploaded media

---

## Known local issues

- [x] ~~`public/storage` pointed at the project's old `htdocs` location, so every
      uploaded image was broken~~ *(fixed 21 Aug — re-linked to the current path)*
- [ ] `C:\xampp\htdocs\EduNest` holds an abandoned Laravel 13 skeleton from a
      false start, using the separate `edunest_v13` database. Safe to delete
      once you are sure nothing there is wanted. See
      [docs/architecture/decisions/0001-two-codebases.md](docs/architecture/decisions/0001-two-codebases.md).
- [ ] `D:\EduNest` is a stale copy from 21 August. Once GitHub is set up it
      becomes redundant and should be removed to avoid editing the wrong one.
- [ ] `README.md` is still Laravel's default and should describe EduNest.
