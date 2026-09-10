---
title: Security & Data Handling Statement
version: "1.0"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-01
status: Awaiting legal review
---

# AkademicNest Security & Data Handling Statement

**Version 1.0 · Effective [TO BE PROVIDED] · Last updated 1 September 2026**

<!-- internal:start -->
> **Draft.** Section 10 lists real weaknesses in the platform as it stands. That
> section exists because a security statement that only lists strengths is not a
> security statement. **Decide deliberately how much of section 10 to publish**
> — the findings should be *fixed* before publication, not deleted from the
> document.
<!-- internal:end -->

---

## 1. Purpose

What AkademicNest actually does to protect the information it holds, verified
against the code on 1 September 2026. Nothing here is aspirational: where a
protection does not exist, it is listed in section 10 instead.

## 2. Authentication

**Passwords**

- Hashed with **bcrypt at 12 rounds**. Never stored in readable form and not
  recoverable by anyone, including the AkademicNest Team.
- Minimum **8 characters, with upper and lower case, a number and a symbol**,
  applied consistently to registration, password change and reset.
- An account created by a school with a temporary password **must** have a new
  password set by its owner before the portal can be used for anything else.
- Changing a password sends a notification to the account holder, including the
  IP address it was changed from.

**Separate account systems**

Pupils, parents/guardians, staff and administrators authenticate through
**separate mechanisms against separate tables**. A pupil credential is not
valid for a staff sign-in, and the separation is structural rather than a
role check applied after the fact.

**Rate limiting**

Sign-in attempts are throttled by identifier, by school and by IP address, on
every portal. Result token lookups are throttled separately, again per school
and per IP. The mobile API is limited to 60 requests a minute, keyed to the
token so one device cannot exhaust another's budget.

**Not implemented**: two-factor authentication. See section 10.

## 3. Authorisation and school isolation

The rule that one school can never reach another school's records is enforced
on the server. A shared check compares the record's school against the
**signed-in user's school taken from the session**, and refuses with a 403 —
it does not filter results, and it does not trust any school identifier
submitted in a request.

The same principle runs throughout:

- **Signatures** are recorded against the signed-in account. There is no signer
  identifier in the accepted input at all — not a form field, not a query
  string, not a route parameter. There is nothing to tamper with.
- **Result tokens** are bound to one pupil and one examination when they are
  created, and the binding is re-validated against the issuing school. There is
  no later moment at which a token decides what it is for.
- **Visitor submissions** are routed to the school whose page they came from,
  taken from the address of the page rather than a form field.
- **Plan restrictions** are enforced by middleware on the routes themselves. A
  feature a school has not paid for is refused at the server, not merely hidden
  in the interface — including at a URL typed directly.

**Honest limitation.** Isolation is enforced by an explicit check in each
controller rather than by a database-level scope applied automatically. It is
applied consistently today, but a new controller that omits the call would not
be caught by anything. See section 10.

## 4. Transport and browser protections

- **HTTPS is enforced in production**: a plain HTTP request is redirected before
  anything else processes it, and **HSTS** is sent so the browser stops trying
  HTTP for a year.
- **Content Security Policy** on every response. `object-src 'none'`,
  `base-uri 'self'`, `form-action 'self'` (an injected form cannot post a
  password elsewhere) and `frame-ancestors 'none'` (the application cannot be
  framed, so clickjacking is not possible).
  **The policy permits inline and evaluated scripts** because the interface
  framework requires it; that part is a real weakening and is listed in section
  10 rather than glossed over.
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`.
- **Permissions-Policy** disables camera, microphone, geolocation, payment and
  USB. Nothing in AkademicNest uses them, so nothing embedded in it can ask.

## 5. Common web attacks

| Attack | Protection |
| --- | --- |
| **SQL injection** | Queries are built through the framework's query builder and ORM with bound parameters. No raw SQL built from user input was found. |
| **Cross-site scripting** | Template output is escaped by default. Escaping has **not** been globally disabled. |
| **Cross-site request forgery** | Token validation on every state-changing request, with a clear recovery path rather than a dead-end error page when a form goes stale. |
| **Session fixation** | The session identifier is regenerated on sign-in. |
| **Automated form abuse** | Public forms carry a hidden trap field, and are rate limited. |
| **Mass assignment** | Models declare explicitly which attributes may be set from a request. |

## 6. File uploads

- The **stored filename is chosen by AkademicNest** — a random UUID. The uploader's
  filename is discarded.
- The **extension is derived from the file's actual content**, not its name, and
  checked against an allowlist. Anything not on the list is stored as `.bin`.
  A file named `.php` cannot be written under that name from either direction.
- **SVG is deliberately excluded.** It is the one image format that can carry a
  script, and these files are served from the school's own origin.
- Size and count limits are enforced (conduct-report attachments: 5 MB, maximum
  four files).
- **EXIF metadata is stripped from uploaded payment receipts**, removing camera
  and location data before the file reaches disk.

**Private storage** — not reachable by URL, served only through an authorised
controller:

- payment receipts;
- conduct-report attachments (photographs a member of the public took of a
  pupil).

**Public storage** — reachable by anyone with the address:

- **pupil photographs, staff photographs, guardian photographs**;
- **signature images**;
- school logos, favicons, facility images, news images, gallery images, hero
  slides, ID card templates, CBT question images.

The addresses are random UUIDs and therefore not guessable, but they are **not
access-controlled**: anyone who obtains a URL can retrieve the file, signed in
or not, and can keep retrieving it. See section 10.

## 7. Result tokens

Designed on the assumption that a token will be passed around.

- Stored as a **one-way hash**, with a separately **encrypted** copy so a school
  can re-display a token it issued. The plain value exists only at the moment of
  issue.
- **Bound to one pupil and one examination at creation.** There is no window in
  which a token is a general-purpose code.
- **Limited number of uses** — five by default, configurable — so a token that
  circulates stops working.
- Can be given an expiry.
- **Every attempt is logged** with the time, outcome, IP address and browser,
  so a school can see whether a token was used and how often.
- Lookups are rate limited per school and per IP.

**If a token is compromised**: it exposes one pupil's result for one examination
and nothing else. A school can see the access log and can revoke the token. It
gives no access to any portal, any other pupil, or any other examination.

## 8. Logging and audit

- **Audit log** — administrative actions with the actor, a description, the
  subject and the IP address. School deletion is recorded before it happens, so
  the entry survives what it describes.
- **Sign-in events** are logged; failed sign-ins are logged.
- **Password resets** are logged and notified to the account holder.
- **Result token access** is logged in full.
- **Page views** record path, referrer, traffic source, device type and **IP
  address** for every request.

Error logs are written to the application's log files. Verbose error display is
controlled by a deployment setting that **must be off in production** — see
section 10.

## 9. Session handling

- Sessions are stored **server-side in the database**, not in the browser.
- School portals sign a user out after **three minutes of inactivity** —
  administrators, staff, pupils and parents alike. These accounts hold pupil
  records and are used on shared devices.
- The timeout deliberately does **not** apply to public school websites; it once
  did, and it silently discarded messages visitors were in the middle of
  writing.
- Signing out invalidates the session server-side and regenerates the token.
- Deleting a school deletes its administrators' active sessions and any pending
  password reset tokens.

## 10. Security & privacy risks requiring remediation

**Found in the code. Ordered by severity. Fix these before publishing a security
statement that a reader might rely on.**

### CRITICAL — Pupil photographs are at unauthenticated public URLs

Photographs of children — along with staff and guardian photographs — are
written to publicly readable storage. The address is an unguessable UUID, but
there is **no access control**: anyone who obtains a URL can retrieve the image
indefinitely, from any network, signed in or not. URLs leak through browser
history, shared screenshots, referrer headers and cached pages.

*Fix:* move photographs to private storage and serve them through a controller
that checks the requester's school and role, as conduct-report attachments
already are.

### CRITICAL — Signature images are at unauthenticated public URLs

The same exposure, applied to signatures. A principal's signature retrieved from
a public URL can be reproduced on any document. This undermines the integrity of
every report card the platform produces.

*Fix:* private storage, served only in the context of an authorised document.

### CRITICAL — Deleting a school leaves its files on disk

School deletion removes the database records but **not** the uploaded files.
Pupil photographs, signatures, receipts and conduct-report attachments belonging
to a deleted school remain on the server indefinitely, with the public ones
still retrievable.

*Fix:* delete the school's storage directories within the deletion transaction.

### HIGH — Payment receipts are sent to a third party abroad, undisclosed

Receipt files are transmitted to Anthropic's API for automated screening.
Receipts carry payer names, bank details and amounts. Schools are not told, no
processor agreement is in place, and no transfer mechanism has been established.

*Fix:* disclose it in the Privacy Policy (drafted), put an agreement in place,
establish a lawful transfer basis, and consider whether screening is worth the
transfer at all.

### HIGH — Terms and Privacy Policy do not exist and consent is not recorded

The registration form requires agreement to a Terms & Conditions, Privacy Policy
and Data Protection Policy — and **all three links point to `#`**. Schools are
agreeing to documents they cannot read. Nothing records **which version** was
accepted or **when**.

*Fix:* publish the documents, link them, and store the accepted version and
timestamp against the school.

### HIGH — API tokens never expire

Mobile API tokens are issued with **no expiry**. A token taken from a lost phone
remains valid indefinitely unless revoked by hand.

*Fix:* set an expiry, prune expired tokens, and give users a way to see and
revoke their own sessions.

### HIGH — No retention limits on logs containing IP addresses

Page views, audit logs and result token access logs grow without limit and are
never pruned. This is an indefinitely growing record of personal data with no
justification for its age.

*Fix:* implement the schedule in the *Data Retention & Deletion Policy*.

### HIGH — Backup position unknown

No backup implementation exists in the codebase. If the hosting arrangement does
not provide one, a single failure destroys every school's records irrecoverably.

*Fix:* confirm, document, encrypt, and **test a restore**.

### MEDIUM — School isolation depends on remembering a call

Tenant isolation is an explicit check written into each controller. Applied
consistently today; a controller added tomorrow that omits it would expose
another school's records with nothing to catch the omission.

*Fix:* add an automatic query scope as defence in depth, keeping the explicit
checks. Consider a test that fails when a school-scoped controller lacks one.

### MEDIUM — No two-factor authentication

Not available for any account, including School Administrators and the AkademicNest
Team — accounts that can read every pupil record in a school, or delete a school
entirely.

*Fix:* offer 2FA, and consider requiring it for the AkademicNest Team.

### MEDIUM — CSP permits inline and evaluated scripts

`script-src` allows `'unsafe-inline'` and `'unsafe-eval'`, which is what the
interface framework requires. This materially weakens the policy's protection
against script injection. The genuinely protective directives are not weakened.

*Fix:* longer-term, move to nonces or hashes. Documented here so nobody reads
"we have a CSP" as more than it is.

### MEDIUM — Blood group is collected without an established basis

Health data about children and staff is collected with no recorded purpose, no
special-category condition established, and no separate access control.

*Fix:* establish why it is needed. If it is for emergencies, restrict who can
see it. If it is not used, **stop collecting it** — the cheapest way to protect
data is not to hold it.

### MEDIUM — Session cookies are not encrypted; secure-cookie default is off

`SESSION_ENCRYPT` defaults to false and `SESSION_SECURE_COOKIE` defaults to
false in the example configuration. Production must override the latter.

*Fix:* set `SESSION_SECURE_COOKIE=true` in production and verify it, and
consider enabling session encryption.

### MEDIUM — Free-text `notes` fields on pupils and staff

Unstructured fields invite the recording of sensitive information — medical
details, behavioural notes, family circumstances — with no separate protection,
no retention rule and no visibility to the person concerned.

*Fix:* label them with guidance on what not to record, and restrict who can see
them.

### LOW — Debug mode

`APP_DEBUG` defaults to true in the example configuration. Left on in production
it would display stack traces and configuration to anyone triggering an error.

*Fix:* verify it is false in production; consider a startup check that refuses
to boot otherwise.

### LOW — No documented incident response procedure

Section 11 describes an intent. There is no runbook, no named owner, no
regulator contact list and no notification templates.

*Fix:* write one before it is needed.

## 11. Security incidents

If unauthorised access, exposure or loss of personal information occurs, or a
provider we rely on suffers a breach, we will:

1. **Contain** — cut off the access, revoke affected credentials and tokens.
2. **Investigate** — establish what happened, what was affected and who.
3. **Assess** — the risk to the people involved, which for pupils' data may be
   significant.
4. **Notify affected schools without undue delay**, with what we know, what we
   are doing, and what they should do — including anything they must pass on to
   parents or staff.
5. **Notify the regulator** where legally required. In Nigeria this is the
   Nigeria Data Protection Commission; statutory deadlines apply.
6. **Record and learn** — what allowed it, and what stops it recurring.

**We do not promise a fixed notification time here.** A commitment made before
an incident that cannot be met during one is worse than none. Statutory
deadlines apply regardless.

To report a vulnerability or a suspected incident: **support@akademicanest.com**. We ask
that you report rather than explore, and we will not pursue good-faith reports.

## 12. Contact

| | |
| --- | --- |
| Security reports | support@akademicanest.com |
| Data protection enquiries | support@akademicanest.com |

---

*Verified against the codebase on 1 September 2026. Every protection listed in
sections 2–9 was confirmed present; every gap in section 10 was confirmed by
looking for the feature, not inferred from documentation.*
