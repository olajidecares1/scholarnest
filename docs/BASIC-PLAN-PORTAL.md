# The Basic-plan portal

How Basic-plan schools are reached, and why it works differently from Standard
and Exclusive.

---

## The problem it solves

Standard and Exclusive schools each have an address of their own:

| Plan | Where the school lives |
| ---- | ---------------------- |
| Standard | `greenfield.akademicnest.com` — its own subdomain |
| Exclusive | `greenfieldschool.com` — its own domain |
| **Basic** | **nothing of its own** |

Basic does not include a public website or a subdomain. That is the plan's main
limitation, and it creates a practical problem: there is no address to print on
a letter to parents.

The Basic portal is the answer. One shared front door for every Basic school,
which asks which school you belong to and forwards you there.

---

## The flow

```
  akademicnest.com/{32-character token}
                 |
                 |   "Enter your school name"
                 v
        Greenfield College
                 |
                 |   matched against Basic-plan schools only
                 v
  akademicnest.com/greenfield-college
                 |
                 v
   the school's sign-in choices:
   School Admin · Staff · Student · Parent
```

Three outcomes when a name is submitted:

| Result | What happens |
| ------ | ------------ |
| One match | Redirected straight to that school |
| Several matches | Shown a list to choose from, with each school's code |
| No match | An error, saying nothing about why |

---

## The token

The 32-character token in the URL comes from `BASIC_PORTAL_TOKEN` in `.env`.

```
akademicnest.com/6219db402a20f65b63358972bd5274cd
```

**It is not a password.** Every Basic school shares it, and it identifies
nobody. Its only job is to stop the entry point being found by someone idly
typing `/portal`, exactly like the per-school portal tokens already used for the
four login pages.

Generate one with:

```powershell
php artisan basic-portal:token          # generate a new one
php artisan basic-portal:token --show   # print the current portal URL
```

The command deliberately does not write to `.env` itself — that file also holds
database credentials, and on a live server the value belongs in the deployment's
secret store.

**With no token set, every Basic portal URL returns 404.** That is deliberate:
failing closed is safer than shipping a predictable default that would be
identical on every AkademicNest installation in the world.

A wrong token also returns **404, never 403**. A 403 would confirm that
something real sits at that path and invite guessing at it.

---

## Plan separation

The three portals share no route, controller or view.

| | Basic | Standard / Exclusive |
| --- | --- | --- |
| Entry point | `/{token}` | Their own subdomain or domain |
| Controller | `Portal\Basic\SchoolFinderController` | `PublicSchoolWebsiteController` |
| School page | `/{slug}` — portal sign-in choices | `/` on their own host — a full website |

**A Standard or Exclusive school cannot be found through the Basic finder**,
even if its name is typed exactly. Two reasons:

1. It would break the separation — those schools are reached directly.
2. It would leak information. Confirming a school exists but is "not available
   here" tells an outsider both that AkademicNest has that customer and roughly what
   they pay for.

If someone reaches `/{slug}` for a Standard or Exclusive school anyway, they are
redirected to that school's real website rather than being served a competing
entry point from the platform root.

---

## The root-level route, and why it is safe

`akademicnest.com/greenfield-college` puts a school slug at the root of the site, in
the same namespace as every top-level path the application owns. A school that
claimed `login` or `dashboard` would be a serious problem.

Three independent things prevent it:

### 1. The route is registered last

It is the final route in `routes/web.php`, after the `require` of `auth.php`.
Laravel matches the first route that fits, so every real route wins.

> **Nothing may be registered after that block.** There is a comment saying so
> in the file. A route added below it would be unreachable.

### 2. The pattern excludes reserved words

`config('basic_portal.reserved_slugs')` lists every name a school may not take —
application paths, infrastructure names, environment names, and the mail-related
names (`mail`, `webmail`, `noreply`) that would otherwise enable convincing
phishing from a trusted domain.

The exclusion is anchored per word:

```php
'(?!(?:portal|login|...|akademicnest)$)[a-z0-9]+(?:-[a-z0-9]+)*'
```

The `(?: ... )$` grouping matters. Written as `(?!portal|login|...|akademicnest$)`
the anchor would apply only to the *last* alternative, and any slug merely
*starting* with a reserved word would 404 — quietly breaking a legitimate school
called "Newspaper College". There is a regression test for exactly that.

### 3. Reserved slugs cannot be created

`School::booted()` skips reserved names when generating a slug, so a school
named "Login" becomes `login-2`. The collision cannot exist in the first place.

---

## How a name is matched

`App\Services\BasicPortalSchoolFinder` runs three tiers and stops at the first
that produces anything:

1. **Exact** — school code, slug, or exact name (case-insensitive)
2. **Prefix** — names starting with what was typed
3. **Contains** — names containing it

Tiering matters. Without it, a school named exactly "Kings College" would be
buried in a list alongside every other school with "college" in its name.

Only schools passing `hasPlanAccess(PlanKey::Basic)` are ever returned. That
check is the app's single shared plan gate, used in PHP rather than duplicated
as a SQL join, so there is only one definition of "is on the Basic plan".

### Against enumeration

The finder is a lookup against customer names, so two things guard it:

- **Rate limiting** — `throttle:20,1` on both routes.
- **Wildcard escaping** — `%` and `_` typed into the box are escaped, so they
  match literally. Without this, submitting `%` would return every Basic school
  on the platform.

  The escape character is `!`, not the usual backslash. MySQL and SQLite
  disagree about backslashes in string literals, so `ESCAPE '\\'` means one
  character to one engine and two to the other — and the second rejects it.
  `!` is special to neither, so production (MySQL) and the test suite (SQLite)
  behave identically.

- At most **8 candidates** are ever shown.

---

## Files

| File | Role |
| ---- | ---- |
| `config/basic_portal.php` | Token and reserved slug list |
| `app/Http/Middleware/ValidateBasicPortalToken.php` | Guards the entry point |
| `app/Http/Controllers/Portal/Basic/SchoolFinderController.php` | The form and the lookup |
| `app/Http/Controllers/Portal/Basic/SchoolLandingController.php` | `/{slug}` |
| `app/Http/Requests/Portal/Basic/FindSchoolRequest.php` | Input validation |
| `app/Services/BasicPortalSchoolFinder.php` | Name matching |
| `app/Console/Commands/GenerateBasicPortalToken.php` | `basic-portal:token` |
| `resources/views/portal/basic/finder.blade.php` | The form |
| `tests/Feature/Portal/BasicPlanPortalTest.php` | 24 tests |

The school page itself reuses `resources/views/school-portal/index.blade.php`,
the existing sign-in hub. The *flow* is separate; there is no reason to maintain
a second copy of the same list of login buttons.

---

## If you change this

- **Never register a route after the landing block** in `routes/web.php`. It
  would be unreachable.
- **Adding a new top-level path?** Add it to `reserved_slugs` too, or a school
  could already own that name.
- Run `tests/Feature/Portal/BasicPlanPortalTest.php` after touching routing —
  it asserts that `/portal/sign-in` and `/up` still resolve to the application
  rather than being swallowed by the school route.
