# EduNest

A multi-tenant school management platform: one installation, many schools, each
with its own staff, pupils, parents, records — and its own public website.

Built on Laravel 12 and PHP 8.2.

---

## What it does

**Five portals**, each with its own guard and its own sign-in:

| Portal | For |
| --- | --- |
| Super Admin | The platform: schools, subscriptions, CMS, media, support |
| School Admin | One school, everything in it |
| Staff | Registers, score entry, assignments, CBT authoring, diary |
| Student | Results, assignments, attendance, timetable, CBT |
| Guardian | Their children's results, attendance, fees, messages |

**Running a school** — pupils, staff and guardians; academic levels, terms,
classes, subjects and offerings; teacher assignments; attendance; examinations,
scores and grade bands; report cards with PDF and Word export; ID cards with
public QR verification; fees, invoices and payments; library, transport,
hostels and co-curricular activities; timetables.

**Computer-based testing** — question papers uploaded as `.docx` or `.pdf` and
read into structured questions locally, with no API key and no network. The
paper's rubric and per-question marks come across with it.

**A public website per school** — a block-based builder, news, events, gallery,
testimonials, facilities and job postings, with custom domains and
verification.

**Three plans**, with per-feature gating, a subscription wizard, top-ups and
student licences the Super Admin is the authority on.

**An HTTP API** for the mobile clients — see [docs/API.md](docs/API.md).

---

## Running it locally

Requires PHP **8.2**, Composer, Node 22+, and MySQL or SQLite.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

Then, in one command:

```bash
composer run dev
```

That runs the web server, a queue worker and Vite together. **The queue worker
matters**: CBT extraction is queued, and without a worker an upload sits at
"Pending" forever.

### Tests

```bash
php artisan test --compact
```

The suite runs against SQLite in memory and needs no database of its own. It
does need `npm run build` to have been run at least once — Blade views call
`@vite`, and `@vite` throws without a manifest.

### Formatting

```bash
vendor/bin/pint
```

CI runs `pint --test`, `composer audit` and the suite on every push.

---

## Documentation

| Document | What it covers |
| --- | --- |
| [docs/API.md](docs/API.md) | The v1 HTTP API the mobile apps are built against |
| [docs/PRODUCTION.md](docs/PRODUCTION.md) | What must change before this is served to real schools |
| [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md) | The audit, its findings, and what closed each one |
| [docs/PASSWORD-RESET-POLICY.md](docs/PASSWORD-RESET-POLICY.md) | Who may reset whose password, and why |
| [docs/BASIC-PLAN-PORTAL.md](docs/BASIC-PLAN-PORTAL.md) | How Basic-plan schools are reached without a website |
| [docs/BASIC-PLAN-STUDENT-LICENCES.md](docs/BASIC-PLAN-STUDENT-LICENCES.md) | How student capacity is sold and enforced |
| [docs/GITHUB.md](docs/GITHUB.md) | Branching, commit style, and the day-to-day workflow |
| [TODO.md](TODO.md) | What is built, what is not, and what to do next |

---

## How the code is arranged

Standard Laravel 12, with a few things worth knowing before you go looking:

- **Routes are split by area.** `routes/web.php` is a manifest naming nine
  files in the order they are registered — and that order is part of the
  behaviour, not tidiness. `routes/api.php` does the same for the API.
- **Tenancy is `school_id`, everywhere.** One check, in
  `AuthorizesSchoolOwnership`.
- **Plan restrictions are one middleware**, `plan_feature:<feature>`, described
  once in the `PlanFeature` enum.
- **URLs carry UUIDs**, never the database's own integer keys.
- **Uploads are named from their content**, not from what the browser called
  the file — `App\Support\StoredUpload`.

---

## Licence

Not open source. All rights reserved.
