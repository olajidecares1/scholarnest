---
title: AkademicNest Team Technical Runbook
audience: Authorised AkademicNest technical and support personnel only
version: 1.0
last_updated: 2026-09-01
classification: Internal
---

# AkademicNest Team Technical Runbook

**Version 1.0 · 1 September 2026 · Internal**

For authorised AkademicNest technical and support staff. **Not to be shared with
schools.** The school-facing book is `school-runbook.md`; nothing here should be
quoted to a school user.

---

## Before you touch anything

1. **Reproduce it first.** Most escalations resolve to a plan limit, a missing
   teacher assignment or an unpaid fee balance, all covered in the school
   runbook. Check section 18 of that book before reaching for a console.
2. **You are in a multi-tenant system.** Every query you write must be scoped to
   one school. A `Student::where(...)` without a `school_id` is how you read the
   wrong school's records.
3. **Read before you write.** Query first, confirm the row is what you think,
   then change it.
4. **Never modify data to "fix" a bug** until you understand why it happened. You
   will hide the cause and it will recur.
5. **Log what you did.** `AuditLog::record()` from a console session, or at
   minimum a written note with the school, the change and the reason.

### Stack

| | |
| --- | --- |
| Framework | Laravel 12 · PHP 8.2 |
| Database | MySQL (SQLite in tests) |
| Session driver | `database` `sessions` table |
| Cache driver | `database` `cache` table |
| Queue driver | `database` `jobs`, `failed_jobs` |
| Storage | Local disks. `public` (URL-reachable) and `local` (private). S3 configured but **not enabled** |
| Mail | Per deployment, **[TO BE PROVIDED]** |
| Hosting | **[TO BE PROVIDED]** |

---

# 1. TRIAGE

```
Is it one school, or all schools?
   ALL  → Platform. Section 2.
   ONE  ↓
Is it one user, or the whole school?
   SCHOOL → Section 3 (tenant state) then the feature section.
   USER   ↓
Is it a security report?
   YES → Section 12. Stop and follow it exactly.
   NO  ↓
Does the school runbook already cover it?
   YES → Walk the school through it. Do not fix by hand what they can fix
         themselves. You will be doing it again next term.
   NO  → Feature section below.
```

## Severity and response

| | Definition | Response |
| --- | --- | --- |
| 🔴 **P1** | Platform down · security incident · data loss · cross-tenant leak | Immediate. Page whoever is on call. Preserve evidence before anything else |
| 🟠 **P2** | One school fully blocked · payments not processing · queue stopped | Same working day |
| 🟡 **P3** | One feature or one user affected, workaround exists | Next working day |
| 🟢 **P4** | Cosmetic, content, single-user confusion | Backlog |

---

# 2. PLATFORM-WIDE

## 2.1, Application returns 500 for everyone

**Severity:** 🔴 P1

**Diagnose, in this order:**

1. **Read the log.** `storage/logs/laravel.log`, most recent entries. The stack
   trace usually names the cause outright.
2. **Confirm `APP_DEBUG=false` in production.** If it is true, that is its own
   P1, stack traces and configuration are being shown to the public. Fix that
   first.
3. **Database reachable?** `php artisan db:show` connection failure is the
   most common cause of a total outage.
4. **Disk full?** A full disk breaks logging, sessions, cache and uploads at
   once and produces confusing symptoms everywhere.
5. **Recent deploy?** Check what shipped last. Roll back before debugging
   forward.
6. **Config cache stale after a deploy?**
   `php artisan config:clear && php artisan cache:clear && php artisan view:clear`,
   then re-cache. A stale config cache after an env change is a classic.

**Do not** clear caches as a reflex on a healthy system, you will lose the
signal that told you what was wrong.

## 2.2, Vite manifest error

**Severity:** 🟠 P2, the application is up but unstyled or broken

`Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest`

Front-end assets were not built for this deploy. Run `npm run build` as part of
the deploy, then clear the view cache. Add it to the deploy pipeline if it is
not there, this recurs otherwise.

## 2.3, Maintenance mode

`CheckMaintenanceMode` middleware runs on the web group. Confirm whether
maintenance was left on deliberately before treating it as an outage.

---

# 3. TENANT STATE, THE FIRST CHECK FOR ANY SINGLE-SCHOOL REPORT

Almost every "school X cannot do Y" resolves here. Run this before anything
else.

```php
$school = App\Models\School::where('slug', 'the-slug')->firstOrFail();

[
  'active'        => $school->is_active,
  'has_active_sub'=> $school->hasActiveSubscription(),
  'plan'          => $school->activeSubscription?->plan?->key?->value,
  'sub_status'    => $school->subscriptionForDisplay()?->status?->value,
  'slot_limit'    => $school->studentSlotLimit(),      // null = uncapped
  'active_pupils' => $school->students()->where('is_active', true)->count(),
  'website'       => $school->website?->is_published,
];
```

**Interpretation:**

| Finding | Meaning |
| --- | --- |
| `active` false | Suspended. Everything content-related is blocked by `EnsureSchoolIsActivated` |
| `has_active_sub` false | No approved subscription. Same effect. Check `subscriptionForDisplay()` pending is different from absent |
| `plan` = basic | No public website, no student/guardian portals, no CBT. **Staff portal still works** |
| `slot_limit` at `active_pupils` | At capacity. Every create and reactivate will be refused |
| `website` false | Public site unpublished, 404 for visitors, correctly |

**Plan gating** is `EnsureSchoolHasFeature` (`plan_feature:<key>` on the route),
resolved through `PlanFeature::requiredPlans()`. That enum is the single source
of truth, do not add a plan check anywhere else.

> **Exclusive cannot be subscribed to** (`PlanKey::isAvailableToSubscribe()`
> returns false for it). If a school reports being unable to buy Exclusive, that
> is correct behaviour, not a fault.

---

# 4. AUTHENTICATION AND SESSIONS

## 4.1, Architecture

Four guards, four tables, deliberately separate:

| Guard | Model | Table |
| --- | --- | --- |
| `web` | `User` | `users` Super Admin and School Admin only |
| `staff` | `Staff` | `staff` |
| `student` | `Student` | `students` |
| `guardian` | `Guardian` | `guardians` |

A credential is valid on exactly one guard. Cross-guard sign-in is structurally
impossible, not a role check.

## 4.2, "User cannot log in"

```php
// Pick the right model for the guard.
$account = App\Models\Staff::where('school_id', $school->id)
    ->where('staff_number', 'ABC/001')->first();

[
  'exists'   => (bool) $account,
  'active'   => $account?->is_active,
  'must_chg' => $account?->must_change_password,
  'last'     => $account?->last_login_at,
];
```

| Finding | Cause |
| --- | --- |
| No row | Account not created, or wrong school |
| `is_active` false | Deactivated → they see "This account has been deactivated" |
| `must_change_password` true | They can sign in but only reach settings and logout, `EnsurePasswordHasBeenChanged` |
| `last_login_at` recent | They **are** signing in. The problem is downstream, assignment, plan, or capacity |

**Never read or reset a password from a console.** Have the School Admin reset it
through the interface, which sets `must_change_password` and notifies the
account holder.

## 4.3, Lockouts

5 attempts per identifier+school+IP, 900-second decay. Admin login additionally
30 per IP.

```php
// Confirm a lockout without clearing it.
Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5);
```

**Do not clear a lockout as routine.** It exists to stop credential guessing.
Clear it only when you have established the lockout was self-inflicted, and note
that you did. If an account is locked and the user swears they typed nothing,
treat it as section 12.

## 4.4, Portal token 404s

Portal sign-in pages sit behind per-school tokens: `portal_student_token`,
`portal_guardian_token`, `portal_staff_token`, `portal_admin_token` on `schools`.
`ValidateSchoolPortalToken` aborts **404** on mismatch, never 403, a 403 would
confirm something is there.

```php
$school->only(['portal_staff_token','portal_student_token','portal_guardian_token','portal_admin_token']);
```

The Basic-plan portal uses one platform-wide token from
`config('basic_portal.token')`. **Empty config fails closed with a 404** rather
than falling back to something predictable.

## 4.5, Idle timeout complaints

`LogsOutIdleUsers`, `IDLE_SECONDS = 180`, guards `web`, `student`, `staff`,
`guardian`. Super Admins are exempt.

**It only runs on routes that require authentication.** It once ran on the public
website and silently discarded visitors' contact-form submissions, if a report
resembles that, check `requiresAuthentication()` still gates it before assuming
the timeout is at fault.

## 4.6, Session table

```sql
SELECT COUNT(*) FROM sessions;
SELECT COUNT(*) FROM sessions WHERE last_activity < UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY);
```

> **Nothing prunes this table.** Expired sessions accumulate indefinitely. Growth
> here is expected, not a symptom, but it is worth capping. See the
> improvement recommendations.

---

# 5. RESULTS AND ACADEMIC DATA

## 5.1, "Result published but the parent cannot see it"

**Check the fee gate first. It is the most common cause and the least obvious.**

`ResultAccessPolicy` withholds a result while the pupil has an outstanding
balance, unless the school recorded a `ResultFeeClearance` for that term.

```php
$policy = app(App\Services\ResultAccessPolicy::class);
$policy->outstandingBalance($student);        // > 0 means withheld
```

A school with no invoices raised owes nothing, so nothing is withheld, that is
what makes the gate safe on plans with no Finance module.

**The release is the school's decision, always.** Do not clear a balance or add a
clearance on their behalf. Point them at school-runbook 4.6.

## 5.2, "Result will not publish"

```php
app(App\Services\ResultCompleteness::class)->blockers($examination, $student);
```

Returns a list naming exactly what is missing. Empty means it will publish.
`advisories()` returns the missing remarks, which are **warnings, not blockers**.

Publishing a class (`ResultRepository::publishClass`) returns
`['published' => n, 'skipped' => n]` it skips incomplete pupils rather than
failing the batch.

## 5.3, Teacher cannot enter scores

Not a permission bug, an assignment gap, nine times in ten.

```php
$staff->classesAsClassTeacher();   // classes they lead
$staff->subjectAssignments();      // [{class_name, subject}]
$staff->scorableClassNames();      // union of both
```

`TeacherAssignment.class_name` is a **string** and must match
`students.class_name` and the examination's class exactly. Whitespace and
casing differences are the single largest source of "empty class" reports.

```sql
-- Find class-name drift within one school.
SELECT DISTINCT class_name FROM students WHERE school_id = ?;
SELECT DISTINCT class_name FROM teacher_assignments WHERE school_id = ?;
```

## 5.4, Grades or positions look wrong

`ExaminationResultCalculator` computes from stored scores and the school's own
`GradeBand` rows. Check the bands for gaps and overlaps before suspecting the
calculator:

```sql
SELECT * FROM grade_bands WHERE school_id = ? ORDER BY min_score;
```

A gap between 69 and 70 leaves 69.5 ungraded. An overlap means one band silently
wins.

## 5.5, Result tokens

`ResultCheckingPin`: `token_hash` (lookup) plus `token_encrypted` (cast
`encrypted`, so a school can redisplay one it issued). Bound to `student_id` and
`examination_id` at creation and re-validated against the issuing school.

```php
$pin->uses_count.'/'.$pin->max_uses;   // default max 5
$pin->status;                          // ResultCheckingPinStatus
$pin->expires_at;
App\Models\ResultTokenAccessLog::where('result_checking_pin_id', $pin->id)->latest()->get();
```

**Never reconstruct or read out a plain token.** The plain value exists only at
issue. If a school needs a working token, they issue a new one.

**Compromised token:** revoke it (Super Admin → Result PINs). Exposure is limited
to one pupil, one examination.

---

# 6. QUEUE AND CBT EXTRACTION

## 6.1, The single most common P2

CBT extraction is queued. It runs locally, PHPWord, smalot/pdfparser, with no
external service and no API key. **It needs a worker running and nothing else.**

```php
app(App\Services\QueueWorkerHealth::class)->isRunning();
```

The worker stamps a heartbeat on every loop via `Queue::looping()` in
`AppServiceProvider`. No recent heartbeat means no worker.

```sql
SELECT COUNT(*) FROM jobs;
SELECT COUNT(*) FROM failed_jobs;
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;
```

**Fix:** start a worker.

```bash
php artisan queue:work            # production, under a supervisor
composer run dev                  # development: starts one alongside the server
```

Uploads made while the queue was down are **not lost**. They process when the
worker returns; the school must not re-upload.

## 6.2, Retrying failed jobs

```bash
php artisan queue:failed
php artisan queue:retry <uuid>
php artisan queue:retry all       # only when you know why they all failed
```

Investigate before retrying all, a job failing on malformed input will fail
again and fill the table.

## 6.3, Upload stuck in a status

`CbtDocumentUploadStatus`: `Pending → Processing → Importing → Completed`, with
`NeedsMapping` and `Failed` as terminal branches.

| Stuck at | Cause |
| --- | --- |
| `Pending` | No worker. 6.1 |
| `Processing` | Genuinely slow, or the worker died mid-job. Check `failed_jobs` |
| `NeedsMapping` | Not stuck. The school must choose an exam body and subject |
| `Failed` | Document could not be parsed. Get the file from the school |

**Note the two-audience messaging** in `CbtExtractionAvailability::warning()` and
`QueueWorkerHealth::stalledMessage()`: they take a `$canOperateTheServer` flag
and default to **false**. Never pass true for anything a school user sees, a
teacher was once shown `php artisan queue:work`.

---

# 7. STORAGE AND UPLOADS

## 7.1, Disks

| Disk | Path | URL-reachable |
| --- | --- | --- |
| `public` | `storage/app/public` | **Yes**: via `/storage` symlink |
| `local` | `storage/app/private` | No, served through controllers |

**On `public`:** pupil, staff and guardian photographs; **signature images**;
logos, favicons, facility, news and gallery images; hero slides; ID card
templates; CBT question images.

**On `local`:** payment receipts; conduct-report attachments; report media.

> 🔴 **Known issue.** Children's photographs and signature images are on the
> URL-reachable disk with unguessable names but **no access control**. Recorded
> in the compliance audit as GAP-01 and GAP-02. Do not describe these as
> "protected" to a school.

## 7.2, Images not loading

1. `php artisan storage:link` a missing symlink breaks every public image at
   once, which is the usual signature of this.
2. Check the file exists on disk at the stored path.
3. Check `APP_URL` it builds the public URL, and a wrong one breaks images on
   tenant domains specifically.
4. Check the CSP: `SecurityHeaders` names `applicationOrigin()` in `img-src`
   alongside `'self'` precisely because schools are served from their own
   subdomains and uploads come from `APP_URL`.

## 7.3, Upload rejected

`StoredUpload::name()` discards the client filename, derives the extension from
**content** via finfo, and checks it against an allowlist. Anything unrecognised
becomes `.bin`. **SVG is deliberately absent**: it can host a script and these
files are served from the school's own origin.

If a school reports a legitimate format being refused, check the allowlist and
the corresponding validation rule together. Adding one without the other is how
the two drift.

## 7.4, Orphaned files

> **Deleting a school does not delete its files.** The cascade clears the
> database, sessions and password-reset tokens; storage is untouched. Compliance
> audit GAP-03.

Until that is fixed, deletion requests need a manual storage sweep. Record what
you removed.

---

# 8. PAYMENTS AND SUBSCRIPTIONS

## 8.1, Flow

Bank transfer only. `PaymentMethod::Paystack` exists in the enum but **is not
integrated**: `StoreTopUpRequest` accepts bank transfer alone. No card data
reaches AkademicNest.

```
School submits → receipt to local disk (EXIF stripped) →
PaymentReceiptScreening (runs on our own servers) → manual review → activation
```

**Screening can only reject, never approve.** That is deliberate: a carefully
edited receipt passes every automated check, and a system that announced
"verified" would train reviewers to stop looking. If a file cannot be read the
upload proceeds with a note that screening did not run.

> Receipts never leave the platform. A PDF's text is read locally and an image is
> checked for being blank or too small. Do not add a call to an outside service.

## 8.2, "Paid but not activated"

```php
$school->subscriptions()->latest()->with('payments')->first();
```

Check `status`, whether a payment row exists, and whether a receipt path is
recorded. A subscription with no payment means the school never uploaded one,
send them to school-runbook 13.1.

## 8.3, Capacity

```php
$alloc = app(App\Services\StudentLicenceAllocation::class);
[$alloc->allocated($school), $alloc->used($school), $alloc->remaining($school)];
```

`allocated` reads `activeSubscription.students_count`, which is initial capacity
**plus every approved top-up**: never a reset. `null` means the plan is not
sold per pupil (Exclusive).

Enforcement is `withCapacity()`, which takes a **database lock and re-counts
inside it**. That closes two holes a single check misses: simultaneous
submissions, and deactivate-then-reactivate. **Do not bypass it**: go through
the approval flow so capacity and billing stay in step.

---

# 9. THE PUBLIC WEBSITE AND TENANT ROUTING

## 9.1, Resolution order

`routes/public.php` registers the custom-domain group **first**, constrained by
Host. Laravel picks the first route matching method+URI regardless of domain
specificity, so a host-agnostic route registered earlier would win on every
tenant domain. **Do not reorder that file** without understanding this.

`routes/school-links.php` is registered **last** because its routes sit in the
root namespace (`/{school:slug}`) and would shadow everything after them.

## 9.2, Website not loading

```php
[$school->is_active, $school->hasActiveSubscription(), $school->website?->is_published,
 $school->hasPlanAccess(App\Enums\PlanKey::Standard, App\Enums\PlanKey::Exclusive)];
```

All four must be true. Basic-plan schools have no public website by design.

## 9.3, Custom domains

Exclusive only. `CustomDomain` with `CustomDomainStatus` and
`CustomDomainSslStatus`; `custom-domains:auto-verify` runs on the schedule from
`config('custom_domain.auto_verify_interval_minutes')`.

**This is the only scheduled task in the application.** If the scheduler is not
running, nothing else breaks, but nothing else is scheduled either, which is
its own problem (section 14).

---

# 10. DATA INTEGRITY

## 10.1, Class-name drift

The most common integrity problem. `class_name` is a free string on `students`,
`teacher_assignments`, `examinations` and `attendance_records`. "JSS 1A",
"JSS1A" and "Jss 1a" are three classes.

```sql
SELECT class_name, COUNT(*) FROM students WHERE school_id = ? GROUP BY class_name;
```

Correct through the interface with the school confirming each mapping. Never
bulk-update class names from a console, you will merge two classes the school
deliberately kept apart.

## 10.2, Guardian details duplicated

`students.guardian_name/phone/email` exist alongside the `guardians` table. Two
copies drift, and correcting one leaves the other wrong, which frustrates a
rectification request. Compliance audit GAP-17.

## 10.3, Double-encoded text

`Arise &amp;amp; Shine` in a school name means text was HTML-escaped twice.

```bash
php artisan text:normalise-double-encoded --dry-run   # always dry-run first
php artisan text:normalise-double-encoded
```

Covers 16 tables of plain-text columns. It decodes until stable.

## 10.4, Cross-tenant contamination

🔴 **P1. Go to section 12. Do not query around it first.**

---

# 11. MONITORING

## What exists

- `storage/logs/laravel.log` application errors
- `audit_logs` administrative actions, actor, IP
- `page_views` path, referrer, device, **IP**, every request
- `result_token_access_logs` IP, user-agent, outcome
- Sign-in success and failure listeners
- Queue heartbeat via `QueueWorkerHealth`

## What does not exist

- No uptime monitoring
- No error aggregation (Sentry or equivalent)
- No alerting of any kind, **including no alert when the queue worker stops**,
  which is the most common P2
- No performance monitoring
- No log rotation policy

Section 14 has the recommendations.

---

# 12. SECURITY INCIDENTS

## 12.1, The sequence

```
1. PRESERVE   Do not delete. Do not "clean up". Snapshot the logs now.
2. CONTAIN    Revoke sessions and tokens. Deactivate accounts. Do not delete them.
3. ASSESS     What was reachable? Whose data? How long?
4. NOTIFY     Affected schools without undue delay. Regulator where required.
5. REMEDIATE  Fix the cause.
6. RECORD     Timeline, decisions, evidence.
```

**Preserve before you contain** where the two conflict. A revoked session that
destroyed the only record of what was reached is a worse position than a session
that lived ten minutes longer.

## 12.2, Cross-tenant data exposure, 🔴 P1

The most serious class of incident here.

1. **Do not** run exploratory queries against the affected records, you add
   noise to the audit trail you are about to rely on.
2. Snapshot `audit_logs`, `page_views` and web server logs for the window.
3. Identify the route and the actor.
4. Check whether `authorizeSchoolOwnership()` is present on that controller
   action. Its absence is the likely cause, isolation is an explicit call, not
   an automatic scope (compliance audit GAP-10).
5. Escalate to engineering. Ship the fix with a regression test.
6. Notify both schools. **Both.**

## 12.3, Compromised account

```php
// Session revocation for a web-guard account.
DB::table('sessions')->where('user_id', $user->id)->delete();
DB::table('password_reset_tokens')->where('email', $user->email)->delete();

// API tokens (Sanctum). Note these never expire on their own.
$user->tokens()->delete();
```

Then deactivate rather than delete, review `audit_logs` for that user, and check
what the role could reach.

> API tokens have **no expiry** (`config/sanctum.php` → `'expiration' => null`).
> A token from a lost device is valid until revoked by hand. Compliance audit
> GAP-06.

## 12.4, Suspected breach

Follow 12.1. **The school notifies its own parents and staff**: AkademicNest is the
processor for that data and notifies the school. Do not contact a school's
parents directly.

Statutory deadlines apply regardless of what any policy says. In Nigeria the
regulator is the Nigeria Data Protection Commission.

---

# 13. BACKUP AND RECOVERY

> ⚠️ **[TO BE PROVIDED], and this is the largest open risk in this document.**
>
> **There is no backup implementation in the codebase.** Whether backups exist,
> their frequency, retention, encryption and restore time depend entirely on the
> hosting arrangement, which is not recorded anywhere in the repository.
>
> **Until this is confirmed and documented, treat every deletion request and
> every data-loss report as unrecoverable**, and say so plainly rather than
> offering hope you cannot back.

Once confirmed, this section must state: what is backed up, how often, where it
is held, how long it is kept, whether it is encrypted, **and when a restore was
last actually tested.** An untested backup is a hypothesis.

## Accidental deletion

Deletion is immediate and permanent. There are **no soft deletes anywhere** in
the application.

1. Establish exactly what was deleted and when, `audit_logs` records school
   deletions before they happen, with a count of what went.
2. Determine whether a backup covers the window.
3. **A restore brings back everything from that point**, including data
   legitimately deleted since. Never restore a whole database to recover one
   record without the school understanding what else returns.
4. Where recovery is impossible, say so early. A school planning around a
   recovery that will not come is worse off than one told the truth on day one.

---

# 14. KNOWN GAPS

Carried from the compliance audit (`resources/legal/00-LEGAL-REVIEW-AND-GAPS.md`).
Support staff should know these exist so they are not diagnosed as new faults.

| # | Gap | Support impact |
| --- | --- | --- |
| GAP-01 | Pupil photographs on URL-reachable storage | Never tell a school these are access-controlled |
| GAP-02 | Signature images likewise | Same |
| GAP-03 | School deletion leaves files on disk | Deletion requests need a manual storage sweep |
| GAP-04 | Receipts transferred to a third party abroad | Disclose if a school asks |
| GAP-06 | API tokens never expire | Revoke by hand on any device-loss report |
| GAP-07 | No log retention, `page_views`, `audit_logs` grow without limit | Expect table growth; not a symptom |
| GAP-08 | Backup position unknown | Section 13 |
| GAP-09 | No data export | A school leaving needs a manual extract; allow real time |
| GAP-10 | Tenant isolation is an explicit call, not a scope | First thing to check on any cross-tenant report |
| GAP-11 | No 2FA anywhere | A password is the only barrier on School Admin and Super Admin accounts |

## Operational recommendations

1. **Alert when the queue worker stops.** The heartbeat already exists; nothing
   watches it. This is the most common P2 and it is currently detected by a
   school reporting a stuck upload.
2. **Prune `sessions`, `page_views`, `audit_logs` and token access logs.**
3. **Add error aggregation and uptime monitoring.** There is none.
4. **Confirm and test backups.** Then rewrite section 13.
5. **Add a boot check refusing to start with `APP_DEBUG=true` in production.**
6. **Build the data export.** It removes a manual, error-prone support task and
   closes GAP-09.

---

# 15. WHAT NOT TO DO

| Never | Because |
| --- | --- |
| Read or reset a password from the console | Passwords are hashed and must stay unknown to us. Use the interface reset |
| Bypass `StudentLicenceAllocation::withCapacity()` | Capacity and billing come apart, and the lock exists for a reason |
| Bulk-update `class_name` | You will merge classes a school kept apart |
| Restore a whole database to recover one record | Everything deleted since comes back too |
| Add a plan check outside `PlanFeature` | That enum is the single source of truth. A second copy will disagree |
| Give a school a shell command | See `$canOperateTheServer`. A teacher cannot run `php artisan` |
| Query around a suspected breach before preserving | You contaminate the audit trail you will need |
| Delete an account involved in an incident | It is the evidence |
| Fix by hand what a school can fix themselves | You will do it again next term, and they still will not know how |
| Contact a school's parents directly | AkademicNest is the processor. The school notifies its own people |

---

*Verified against the codebase on 1 September 2026. Where this document and the
code disagree, the code is right and this document is a bug, fix it.*
