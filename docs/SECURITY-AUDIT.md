# Security audit — 21 August 2026, closed out 26 August

A first pass over ScholarNest against the security requirements in the project
brief. Everything below was checked against the actual code, not assumed.

**Overall: this codebase is in good shape.** 1,659 tests pass, tenant isolation is
applied consistently, and several things the brief asks for are already done
properly. The findings are real but none of them are emergencies.

---

## What is already correct

These were checked and need no work.

### Tenant isolation is consistently enforced

48 controllers accept a school-owned model through route-model binding. That is
the dangerous pattern in a multi-tenant app: Laravel will happily fetch *any*
student by id, including one belonging to another school, unless the controller
checks.

45 of the 48 check. The pattern used is:

```php
private function authorizeStudent(Student $student): void
{
    abort_unless($student->school_id === auth()->user()->school_id, 403);
}
```

The three that do not check are all correct to skip it:

| Controller | Why it is fine |
| ---------- | -------------- |
| `SuperAdmin\CmsController` | Super Admin works across all schools by design |
| `SuperAdmin\SupportTicketController` | Same |
| `IdCardVerificationController` | Deliberately public so a printed QR code can be scanned by anyone; documented in the class |

List queries use the relationship (`$school->students()`), which is scoped by
construction and cannot leak.

There are tests for this, for example *"a school admin can only view their own
school tickets"*.

### Sequential ids are not exposed

`SuperAdmin\UuidRouteKeysTest` asserts that schools, media, support tickets and
CMS pages are reachable by uuid and **not** by raw id. This is the right call
and it is tested.

### Login throttling matches the brief

`LoginRequest` allows 5 attempts per 15 minutes, exactly as asked, and fires
Laravel's `Lockout` event. The limiter is cleared on success.

### Password reset meets the brief

- Expires after **60 minutes** (`config/auth.php`), for all four brokers
  (admin, students, staff, guardians)
- Tokens are hashed at rest and single-use — Laravel's broker handles both
- Reset activity is logged by `LogPasswordReset`

### Sign-ins are audited

`LogSuccessfulLogin`, `LogFailedLogin` and `LogPasswordReset` write to
`audit_logs`, which holds 557 entries.

### Uploads are re-encoded, not just accepted

`ImageOptimizer` re-encodes every image through GD. This is stronger than
checking a MIME type, because it does two things at once:

- **strips EXIF metadata**, as the brief requires
- **proves the file is genuinely an image** — a disguised payload will not
  survive being decoded and re-encoded

It also downscales anything over 2560px.

---

## Findings

### 1. Password spraying is not rate limited — *medium*

`LoginRequest::throttleKey()` combines the email and the IP address:

```php
Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
```

This stops someone guessing many passwords against **one** account. It does
nothing about the opposite and more common attack: trying **one** common
password (`Password123!`) against thousands of different accounts from one
address. Each attempt uses a different email, so each gets its own counter and
none ever reaches 5.

The login route has no `throttle` middleware either, so there is no second line
of defence:

```php
Route::post(R::uri('login'), [AuthenticatedSessionController::class, 'store']);
```

**Fix:** add a second, wider limiter keyed on IP alone — roughly 30 failures per
15 minutes. It must be generous, because a whole school can share one internet
connection and several teachers may mistype passwords in the same window.

### 2. Password reset requests are not rate limited per IP — *medium*

`password.email` has no throttle. Laravel's broker enforces 60 seconds between
requests **for the same email address**, but nothing limits one address
requesting resets for thousands of different accounts.

That allows mass password-reset emails to be triggered from ScholarNest — which is
both an abuse of the school's users and a fast route to the mail provider
blocking the platform's sending domain.

Note the admin reset flow already does this correctly with `throttle:10,1`. The
fix is to extend the same treatment to the main flow.

### 3. Stored filenames use the client-supplied extension — *low, but fix it*

14 upload sites build the stored filename like this:

```php
$file->storeAs('students', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');
```

The uuid part is right — the original filename is correctly discarded. But the
extension comes from the browser, which means the *attacker* chooses it.

**This is not currently exploitable**, and it is worth being precise about why.
Laravel's `image` and `mimes` rules call `shouldBlockPhpUpload()`, which rejects
`php`, `php3`, `php4`, `php5`, `php7`, `php8`, `phtml` and `phar` based on the
client extension. So the obvious attack is already blocked by the framework.

The reason to fix it anyway is that the safety depends on a framework blocklist
that the calling code knows nothing about. Any upload site that is ever added
without an `image`/`mimes` rule silently loses the protection, and the blocklist
does not cover every extension that some server configurations will execute
(`.pht`, `.phps`, `.cgi`).

**Fix:** derive the extension from the file's actual content instead:

```php
$extension = $file->extension();   // guessed from the real MIME type
```

### 4. Nothing prevents PHP execution in upload directories — *medium*

The brief asks for this explicitly. There is no `.htaccess` in
`storage/app/public` or `public/storage`.

Uploaded files are served from `public/storage`, a symlink into
`storage/app/public`. If a file that Apache is willing to execute ever lands
there — through finding 3, a future upload path, or a misconfiguration — it
runs as PHP.

This is defence in depth: it costs almost nothing and it removes the entire
class of problem rather than one instance of it.

**Fix:** add an `.htaccess` to the upload root that disables PHP handling, and
serve user uploads through a controller rather than directly where practical.

### 5. Production configuration is not documented — *medium*

`.env` currently reads:

```
APP_DEBUG=true
SESSION_ENCRYPT=false
```

with `SESSION_SECURE_COOKIE` not set at all.

All three are fine on a laptop. All three are dangerous in production:
`APP_DEBUG=true` prints stack traces containing database credentials to
visitors, and without `SESSION_SECURE_COOKIE=true` the session cookie will be
sent over plain http.

Nothing currently records that these must change at deployment, so the person
deploying has to already know.

**Fix:** document the required production values, and add a startup check that
refuses to boot with `APP_DEBUG=true` when `APP_ENV=production`.

### 6. Authorization is spread across three mechanisms — *low, architectural*

There is one Policy (`SubscriptionPolicy`). Everything else is enforced by
middleware (`EnsureUserIsSchoolAdmin`, `EnsureHasPermission`, and 19 others)
plus per-controller `abort_unless` helpers.

This works today — the 941 tests demonstrate that. The cost is that answering
"who may edit a student?" means checking a route definition, a middleware and a
private controller method, and the same `abort_unless` line is repeated in 45
files.

**Fix (gradual):** move the repeated ownership check into a shared trait or a
Policy so it is written once. This is a refactor, not a bug fix, and should
happen module by module rather than in one sweep.

### 7. `routes/web.php` is 78 KB in one file — *low, maintainability*

Not a security issue, but it makes reviewing what is exposed hard, and reviewing
what is exposed is a security activity.

**Fix:** split by area (`routes/school-admin.php`, `routes/student.php`, …) and
load them from `bootstrap/app.php`.

---

## Status

All seven findings are closed.

| # | Finding | Closed by |
| --- | --- | --- |
| 1 | Password spraying not rate limited | per-IP login limiter, 30 failures / 15 min |
| 2 | Reset requests not rate limited per IP | reset limiter |
| 3 | Stored filenames use the client extension | `App\Support\StoredUpload` — content-derived, allowlisted |
| 4 | PHP execution not blocked in upload directories | `.htaccess` in the upload root |
| 5 | Production configuration undocumented | `App\Support\ProductionConfiguration` + [PRODUCTION.md](PRODUCTION.md) |
| 6 | Ownership check repeated in 45 places | `AuthorizesSchoolOwnership` |
| 7 | `routes/web.php` was 94 KB | split into nine files by area |

Findings 3, 6 and 7 each carry a test that fails if the old pattern returns, so
they close rather than merely get fixed.

---

## Not yet audited

This was a first pass. These have still not been looked at:

- The Student, Staff and Guardian portal login flows (four separate guards).
  Their password-reset restrictions are audited and enforced - see
  [PASSWORD-RESET-POLICY.md](PASSWORD-RESET-POLICY.md) - but the sign-in paths
  themselves are not.
- `PortalSessionBroker` and `ValidateSchoolPortalToken`
- The public misconduct-reporting upload path (video handling)
- CBT document import, which now parses uploaded `.docx` and `.pdf` locally -
  a parser reading untrusted files in-process is worth its own pass
- CSRF coverage on the portal routes
- Whether `EnsureHasPermission` can be bypassed by direct route access
- `ReportController::create` lists every school on a public page, publishing
  the full customer list

Also outstanding from finding 6: roughly twenty checks of a different shape -
"these two records belong to the same school as each other" rather than "this
record is mine". That is a second rule and wants its own thinking.
