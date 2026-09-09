# Password reset and account recovery policy

Who is allowed to reset a password in AkademicNest, and how that restriction is
enforced.

---

## The rule

**Only a School Admin may reset the password of a Staff member, Student or
Parent/Guardian — and only for accounts belonging to their own school.**

Those three account types have **no self-service password recovery at all**.
There is no "Forgot password?" link on their login pages, and no route behind
one. If they forget their password or get locked out, they contact their School
Admin.

School Admins themselves are different: they *do* have a self-service reset
flow, because there is nobody above them inside the school to ask.

| Account type | Can reset their own forgotten password? | Who resets it |
| ------------ | --------------------------------------- | ------------- |
| Super Admin | Yes | Self-service |
| School Admin | Yes | Self-service (link + 6-digit code) |

| Staff | **No** | Their School Admin |
| Student | **No** | Their School Admin |
| Parent/Guardian | **No** | Their School Admin |

---

## Why it is built this way

Schools are not ordinary consumer software. Students share devices, parents
share email addresses with their children, and staff email addresses are often
handed out and reused. A self-service reset link sent to an email address is
only as trustworthy as that address, and in a school those addresses frequently
are not controlled by the person they belong to.

Routing recovery through the School Admin means a human who knows the person
verifies who is asking, in a building where they can be recognised.

---

## How it is enforced

The rule is enforced in four independent places. Defeating the interface
achieves nothing, because the interface is not what enforces it.

---

## How the administrator flow works

Laravel's own password broker, with one addition.

```
Forgot password?  ->  enter email  ->  always the same answer
                                        ↓
                          email: "Reset Your AkademicNest Password"
                          [ Reset Password ]  +  6-digit code
                                        ↓
                      reset page: code + new password + confirm
                                        ↓
                          password updated  ->  sign in
```

**Laravel owns the token.** It generates it, decides when it expires
(`auth.passwords.users.expire`, 60 minutes), refuses it once used, and replaces
it when a new one is requested. None of that is re-implemented here.

**The code is the addition, and it is derived rather than stored.** It is an
HMAC of the token under the application key — see `App\Support\PasswordResetCode`.
That means it expires with the token, is replaced with the token, and is deleted
with the token, without a second table that could disagree with the first.
Someone holding the link holds the token and nothing else; without the key they
cannot compute the code, so the link alone remains insufficient.

Why a code at all: the link travels through mail servers, sits in an inbox that
may be shared or open on a staffroom screen, and leaks through referrer headers
and browser history. Requiring the code means whoever resets the password had to
read the email body, not merely acquire the URL from it.

**What the request endpoint never reveals.** The answer is the same sentence
whether the address has an account, has no account, or asked too recently.
Laravel's default says "We can't find a user with that email address", which
turns the form into a free way to test who is a AkademicNest administrator.

**Where the link points.** The URL is built from `APP_URL`, not from the request.
Laravel's `route()` takes its host from the incoming request, so a forged Host
header on the forgot-password endpoint would otherwise put an attacker's domain
in the victim's email — and the victim would hand over their token by clicking
it.

**What is recorded.** Every request is written to the application log, including
the ones that matched nothing, because a run of misses is what enumeration looks
like. Successful resets additionally write an audit entry and send the account
holder a "your password was changed" notification — the one message that reaches
somebody whose account was taken by whoever controls their inbox.

> **There used to be two flows.** A second, parallel reset — its own token table,
> its own broker, its own four pages — existed alongside this one, and nothing
> linked to it. The sign-in page pointed at the route *without* the verification
> code, so every administrator who ever clicked "Forgot password?" used the
> weaker path while the stronger one sat unreachable and unaudited. The code
> moved onto the linked route and the parallel flow was deleted.

### 1. The routes do not exist

There is no forgot-password route registered for the `student`, `staff` or
`guardian` guards. A request cannot reach a handler that was never defined,
whatever it is crafted to look like.

Asserted by `tests/Feature/Auth/NoSelfServicePasswordResetTest.php`.

### 2. The reset endpoints require a School Admin

The endpoints that *do* reset these passwords live behind two middleware:

```php
Route::middleware(['school_admin', 'school_activated'])->group(...)
```

`school_admin` is `EnsureUserIsSchoolAdmin`, which aborts with 403 unless the
request's user is on the `web` guard **and** has the `SchoolAdmin` role.

A Student, Staff member or Guardian signed in to their own portal is
authenticated on a *different guard*, so they are not a `web` user at all.
Posting straight at the route with developer tools returns **403**, verified by
test.

Notably this also blocks **Staff**, including teachers. Being school staff is
not sufficient; only the School Admin role qualifies.

### 3. Every reset re-checks the school

Passing the role check is not enough. Each handler re-verifies that the target
account belongs to the acting admin's own school:

```php
abort_unless($student->school_id === auth()->user()->school_id, 403);
```

This matters because the account is resolved from the URL. Without this check, a
School Admin at one school could swap the identifier in the URL for a student at
another school and reset their password. That check is what makes it a 403
instead.

### 4. Temporary passwords expire on first use

When an admin sets a password, the account is flagged `must_change_password`.
The `EnsurePasswordHasBeenChanged` middleware then blocks every portal page
except the settings screen and logout until the user chooses their own password.

Without this, the admin-chosen password would go on working indefinitely — a
credential known to at least two people, which stops being a password in any
meaningful sense.

The settings page deliberately stays reachable. If it did not, the user would be
redirected in a loop with no way to do the thing being demanded of them.

---

## What is recorded

Every reset writes an `audit_logs` row containing:

| Field | What it holds |
| ----- | ------------- |
| `user_id`, `user_name` | The administrator who performed the reset |
| `subject_type`, `subject_id` | The account that was reset |
| `action` | `password.reset` |
| `created_at` | When |
| `ip_address` | Where from |

When a user later changes their own password, that is logged separately as
`password.changed`.

**Passwords never appear in the log** — not the new one, not a hash of it, not
its length. A log containing passwords is a list of live credentials.

---

## The login pages

The Staff, Student and Guardian login pages each display:

> Forgot your password? Please contact your School Admin to reset your account.

This is deliberately a plain statement with no link. Telling someone what to do
instead is what stops them hunting for a reset page that does not exist.

---

## Tests

| File | Covers |
| ---- | ------ |
| `tests/Feature/Auth/NoSelfServicePasswordResetTest.php` | The routes do not exist; no links on the pages |
| `tests/Feature/Auth/PasswordResetAuthorityTest.php` | Everything else: who may reset, cross-school refusal, bypass attempts from every guard, forced change of temporary passwords, audit logging, and the login-page message |

Together, 29 tests.

---

## If you change any of this

Two mistakes would silently break the policy:

1. **Adding a password-reset route for a portal guard.** Even behind a hidden
   link, the route existing is the vulnerability.
2. **Adding a new admin endpoint that touches a portal password without the
   `school_id` check.** The role check alone does not prevent one school
   reaching into another.

`PasswordResetAuthorityTest` catches both, so run it after touching anything in
this area.
