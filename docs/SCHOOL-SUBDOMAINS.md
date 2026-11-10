# School subdomains

Every Standard and Exclusive school has its own website address:

```
https://akademicanest.com                    the platform (never a school)
https://greenfield.akademicanest.com         a Standard or Exclusive school
https://greenfieldschool.com                 an Exclusive school's own domain, once verified
```

One Laravel application serves all of them. No school has its own routes,
codebase, database or DNS record.

---

## The chain a request goes through

```
browser
  -> DNS           *.akademicanest.com  (Hostinger zone, wildcard record)
  -> Laravel Cloud edge  (Cloudflare: TLS with the *.akademicanest.com certificate)
  -> Laravel Cloud compute, the one application
  -> RedirectToCanonicalHost    www.akademicanest.com -> akademicanest.com
  -> routes/public.php          the {tenantDomain} group matches any host that is not the platform
  -> EnforceTenantHostBoundary  platform pages go back to the platform; another school's account is refused
  -> ResolveTenantFromCustomDomain -> App\Services\Tenancy\TenantResolver
        resolved      -> bind the School, carry on
        unavailable   -> status page (503 suspended/expired/pending, 404 Basic)
        not found     -> status page (404)
  -> the same controllers as the /p/{portal_key} paths, given the resolved School
```

If any link in that chain is missing, no subdomain works. **DNS alone is not
enough**, and neither is the code alone.

---

## How the subdomain is chosen

`School::availableSubdomain()`, when the school row is created (registration
and the Super Admin "Add school" form both create rows the same way):

1. The school's **name**, transliterated to ASCII and lower-cased.
2. Everything that is not `a-z` or `0-9` is **dropped**, not replaced:
   "Greenfield International School" -> `greenfieldinternationalschool`,
   "GodStime Int'L School" -> `godstimeintlschool`. No hyphens, by design.
3. Trimmed to 63 characters, the DNS maximum for one label.
4. If the label is reserved (`www`, `mail`, `admin`, `api`, see
   `config/basic_portal.php`) or already taken, a number is appended:
   `greenfield`, `greenfield2`, `greenfield3`.
5. Stored in `schools.subdomain`, which has a **unique index**, so two schools
   can never hold the same address even under a race.

Every school gets a subdomain value, whatever its plan. Whether that address
is *served* is decided per request by the plan, so a Basic school that
upgrades to Standard is live at once, with no migration.

A Super Admin can change it on the school's page (**Schools -> View ->
Website Address**). The same rules apply. The old address stops resolving
immediately (there are no aliases), and the change is written to the audit log.

---

## Who is served

| Plan / state | `school.akademicanest.com` | Own domain |
| --- | --- | --- |
| Basic | 404 (Basic has no website) | no |
| Standard, active | website, portals, `/results` | no |
| Exclusive, active | website, portals, `/results` | yes, once verified |
| Suspended (`is_active = false`) | 503 status page | 503 status page |
| No approved subscription (expired, rejected, pending) | 503 status page | 503 status page |
| Unknown label | 404 status page | 404 |

The school's data is never deleted by any of these. The status pages show no
school content.

---

## Pages on a school's subdomain

All reuse the existing controllers:

```
/                  /about        /admissions   /facilities   /contact
/news              /news/{post}  /events       /gallery      /jobs
/results           result checking: School ID, then the result token
/portal            portal hub and sign-in
```

Links on those pages are built with `School::publicUrl()`, so they stay on the
subdomain. Visiting a school's old `/p/{portal_key}/...` path 301-redirects to
the same page on its subdomain.

---

## Isolation, and what enforces it

- **The host decides the school.** `TenantResolver` reads only the hostname.
  Nothing in a form, query string or route parameter can change which school a
  subdomain serves.
- **Queries are scoped by the resolved school**, as everywhere else in the
  application (`AuthorizesSchoolOwnership`). Result checking looks pupils and
  tokens up only within the resolved school.
- **Sessions are per host.** `SESSION_DOMAIN` stays `null`. Setting it to
  `.akademicanest.com` would share one session cookie across every school.
- **`EnforceTenantHostBoundary`** refuses a signed-in account whose school is not
  the host's school, and sends the Super Admin console and registration back to
  the platform host so they never share an origin with a school's website.
- **The tenant is request-scoped.** `CurrentTenant` and `TenantResolver` are
  `scoped` bindings, emptied between requests.
- **Caching.** The application caches nothing per school. Laravel Cloud's edge
  caches static file types by full URL, host included, and never caches a
  response carrying `Set-Cookie`, which every page does. The status pages send
  `Cache-Control: no-store`.

---

## Production setup (Laravel Cloud + Hostinger DNS)

AkademicNest runs on **Laravel Cloud**. Hostinger holds the **DNS zone and
email** only. Both need changing, in this order.

### 1. Laravel Cloud: add the wildcard domain

Environment **production** -> **Domains** -> **Add domain** -> `*.akademicanest.com`.

Cloud then shows the DNS records it needs. Typically there are three:

| Type | Name (in Hostinger) | Value |
| --- | --- | --- |
| CNAME | `*` | the origin target Cloud shows |
| TXT | `_cf-custom-hostname` | the ownership token Cloud shows |
| CNAME | `_acme-challenge` | the DCV delegation target Cloud shows |

Wildcards need **pre-verification** because the zone is not on Cloudflare.
Custom domains count against the plan's allowance (Starter: 10).

### 2. Hostinger: create those records

hPanel -> Domains -> akademicanest.com -> **DNS / Nameservers** -> DNS records.
Add each record exactly as Cloud shows it.

- **Keep the `_acme-challenge` CNAME permanently.** It is how the wildcard
  certificate renews, and removing it breaks renewal silently months later.
- Use exactly the record type and value Cloud shows for `*` (CNAME or A). Do
  not improvise one, or Cloud will not verify the wildcard or issue its
  certificate.
- The existing `www` CNAME, the `A @` record and the mail records stay as they are.
- No per-school record is ever needed.

Hostinger's "Subdomains" tab is for Hostinger web hosting and is not used here.

### 3. Laravel Cloud: set the variable, redeploy

Settings -> Environment -> Custom environment variables:

```
TENANT_BASE_DOMAIN=akademicanest.com
```

Leave `SESSION_DOMAIN` unset (null). Environment variable changes need a
redeploy.

### 4. Verify

```
php artisan production:urls        (Cloud -> Commands)
```

Then, from a browser:

- `https://akademicanest.com` shows the platform
- `https://<a standard school's subdomain>.akademicanest.com` shows that school, padlock valid
- `https://doesnotexist.akademicanest.com` shows "No school website at this address"
- `https://www.akademicanest.com` lands on `https://akademicanest.com`

`dig +short anything.akademicanest.com` must return an answer, not NXDOMAIN.

---

## Local development

`lvh.me` and every name under it resolve to `127.0.0.1`, so subdomains work
with no hosts-file editing:

```
APP_URL=http://lvh.me:8000
TENANT_BASE_DOMAIN=lvh.me
SESSION_DOMAIN=null
```

```
php artisan serve --host=0.0.0.0 --port=8000
```

Then open `http://lvh.me:8000` for the platform and
`http://greenfield.lvh.me:8000` for a school. The school needs an approved
Standard or Exclusive subscription and a published website.

The tests pin their own hosts and need none of this:
`tests/Feature/Public/TenantSubdomainIsolationTest.php`.
