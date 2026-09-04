---
title: Audit Summary, Implementation Gaps & Legal Review Flags
version: 1.0
audit_date: 2026-09-01
branch: dev
status: Internal — not for publication
---

# ScholarNest — Audit Summary, Implementation Gaps & Legal Review Flags

**Audited 1 September 2026 against the `dev` branch.**
Internal document. Not to be published alongside the policies.

---

## PART A — FINAL AUDIT SUMMARY

### A. What ScholarNest collects

**Schools**: name, slug, address, phone, email, logo, favicon, billing details,
subscription plan, pupil capacity, payment records and proof-of-payment
documents.

**School Administrators**: name, email, username, phone, password (hashed),
profile photograph.

**Pupils** — *including children*: name, admission number, gender, date of
birth, class, house, address, phone, email, photograph, admission date, free-text
notes, **blood group**, portal password (hashed), plus guardian name, phone and
email held directly on the pupil record.

**Parents/guardians**: name, guardian number, email, phone, photograph, password
(hashed), linked children.

**Staff**: name, staff number, gender, date of birth, role, department,
qualification, employment date, **emergency contact name and phone**, address,
phone, email, photograph, **blood group**, free-text notes, password (hashed).

**Academic**: examinations, subjects, scores, computed grades and positions,
teacher and principal remarks, report cards, attendance, assignments and
submissions, CBT tests/questions/attempts/answers, timetables, diary entries,
co-curricular activities, library loans, transport assignments, hostel
allocations, fee structures, invoices, fee payments.

**Result tokens**: hashed + separately encrypted, bound to one pupil and one
examination, with an access log carrying IP address and user-agent.

**Signatures**: an image drawn by an administrator or member of staff.

**Visitors to public school websites**: contact enquiries (name, email, phone,
subject, message) and pupil conduct reports (reporter name, description,
location, up to four attachments).

**Technical**: IP address on **every** request via page views; IP in audit logs;
IP and user-agent in result token logs; sessions; API tokens.

### B. Where it is stored

- **MySQL** in development and production (SQLite in tests).
- **Private disk** (`storage/app/private`, not URL-reachable): payment receipts,
  conduct-report attachments, report media.
- **Public disk** (`storage/app/public`, URL-reachable): **pupil, staff and
  guardian photographs**, **signature images**, logos, favicons, facility
  images, news images, gallery images, hero slides, ID card templates, CBT
  question images.
- **S3 is configured but not enabled**; the default filesystem is local.
- Sessions in the database; cache and queue in the database.
- Hosting provider and region: **[TO BE PROVIDED]** — not determinable from code.

### C. Who can access it

| Party | Reach |
| --- | --- |
| ScholarNest Team (Super Admin) | All schools; can delete a school and everything in it. Granular roles exist |
| School Administrator | Everything in their own school |
| Staff | Their school, scoped by role and assignment |
| Pupil | Own results, attendance, assignments, notices, timetable |
| Parent/guardian | Linked children's records (Standard+) |
| Result token holder | One pupil, one examination, limited uses |
| Visitor | Public website only |
| **Another school** | **Never** — enforced server-side, refuses rather than filters |

Four separate authentication systems against separate tables: administrators,
staff, pupils, guardians.

### D. How it is protected

bcrypt (12 rounds); 8+ character passwords with mixed case, number and symbol;
per-controller school-ownership checks that refuse with 403; HTTPS enforced in
production with HSTS; CSP, `frame-ancestors 'none'`, nosniff, Referrer-Policy,
Permissions-Policy; CSRF tokens; escaped output; parameterised queries; upload
extensions derived from content against an allowlist with SVG excluded; EXIF
stripped from receipts; rate limiting on sign-in, result lookups and the API;
three-minute idle timeout on school portals; honeypots on public forms; audit
logging.

**Absent**: two-factor authentication, application-level database encryption
beyond passwords and result tokens, automatic tenant query scoping, session
cookie encryption, verified backups.

### E. What third parties receive data

| Party | Receives | Note |
| --- | --- | --- |
| **Anthropic** | **Payment receipt files** + school name | **Cross-border. Undisclosed to schools. No agreement.** |
| Bunny Fonts | Visitor IP + user-agent | Portals |
| Google Fonts | Visitor IP + user-agent | Public school websites, when a Google font is chosen |
| Email provider | Recipient address + message | **[TO BE PROVIDED]** |
| Hosting provider | Everything | **[TO BE PROVIDED]** |

**Confirmed absent**: analytics of any kind, advertising networks, card
processors (Paystack is named in the interface but **not integrated**), CDNs.

### F. Sessions and cookies

Cookies: `scholarnest-session` (necessary, HttpOnly, SameSite=Lax, 120 min),
`XSRF-TOKEN` (necessary, JS-readable by design), `scholarnest_portal` (functional,
HttpOnly, 1 year, holds `schoolId:guard`), `remember_web_*` (functional, only if
the box is ticked).

Browser storage: `theme`, `websiteEditTab` — both `localStorage`, never sent to
the server.

Sessions are server-side; regenerated on sign-in; **school portals time out
after 180 seconds of inactivity**; the public website is exempt.

**No cookie consent mechanism exists.**

### G. How school data is handled

Entered by the school, isolated per school, displayed to users the school
authorises, used to generate report cards and ID cards, never shared between
schools, never used for advertising or model training. Plan restrictions are
enforced by middleware on the routes, not merely hidden in the interface.

### H. How children's data is handled

Same controls as all school data, plus: result tokens are bound and use-limited;
conduct-report attachments (photographs of children taken by the public) go to
**private** storage.

**But**: pupil photographs are on **public** storage; blood group is collected
with no established basis; free-text `notes` fields invite sensitive content
with no extra protection; parental notification is entirely the school's
responsibility and nothing in the platform prompts it.

### I. How data is retained and deleted

**Retention: effectively indefinite.** No scheduled pruning of anything. Audit
logs, page views (with IP), result token access logs and expired sessions all
accumulate without limit.

**Deletion: immediate and permanent.** No soft deletes anywhere. Deleting a
school cascades the database and clears sessions and password reset tokens — but
**leaves every uploaded file on disk**.

**Export: not implemented.** A school cannot get its records out.

### J. Major legal and privacy risks

1. Children's photographs at unauthenticated public URLs.
2. Signature images at unauthenticated public URLs.
3. Undisclosed cross-border transfer of payment receipts to Anthropic.
4. Consent collected for documents that do not exist (links point to `#`).
5. Health data (blood group) about children with no established basis.
6. No retention limits on personal data in logs.
7. No data export — portability rights cannot be met, and a school leaving
   cannot take its records.
8. Files surviving deletion of the school they belonged to.
9. No processing agreement with schools; none with Anthropic.
10. Backup position unknown; possible total-loss risk.

### K. Critical technical fixes before publishing the policies

| # | Fix | Why it blocks publication |
| --- | --- | --- |
| 1 | Move pupil/staff/guardian photographs to private storage behind an authorised controller | The Privacy Policy would otherwise have to disclose that children's photographs are publicly retrievable |
| 2 | Move signature images to private storage | Same, plus it undermines document integrity |
| 3 | Delete storage directories when a school is deleted | The deletion policy cannot honestly promise deletion |
| 4 | Publish the documents and link them from registration | Consent is currently being collected for nothing |
| 5 | Record accepted version + timestamp against the school | Acceptance is otherwise unprovable |
| 6 | Disclose the Anthropic transfer; put an agreement and transfer basis in place | Undisclosed cross-border transfer of personal data |
| 7 | Set an expiry on API tokens | |
| 8 | Confirm and document the backup arrangement | The retention policy cannot be completed without it |
| 9 | Set `SESSION_SECURE_COOKIE=true` and `APP_DEBUG=false` in production | |
| 10 | Decide the blood group question — restrict it or stop collecting it | |

---

## PART B — PRIVACY & LEGAL IMPLEMENTATION GAPS

### GAP-01 · Pupil photographs on public storage — **CRITICAL**

**Where**: `StudentController::storePhoto()`, `StaffController`,
`GuardianController` — all use `storeAs(..., 'public')`.
**Why it matters**: photographs of children retrievable by anyone with the URL,
indefinitely, with no authentication. URLs leak via history, screenshots,
referrers and caches. The single most serious privacy issue in the platform.
**Fix**: private disk + a controller that checks school and role, as
`MisconductReportController` already does.

### GAP-02 · Signature images on public storage — **CRITICAL**

**Where**: `SignatureImage::store()` → `Storage::disk('public')`.
**Why it matters**: a principal's signature can be retrieved and reproduced on
any document, undermining every report card the platform issues.
**Fix**: private storage, served only in the context of an authorised document.

### GAP-03 · Files survive school deletion — **CRITICAL**

**Where**: `SchoolController::destroy()` and `School::booted()` — the deleting
hook clears sessions and password reset tokens but touches no storage.
**Why it matters**: pupil photographs, signatures, receipts and conduct-report
attachments of a deleted school remain on disk, the public ones still
retrievable. Deletion cannot honestly be described as deletion.
**Fix**: delete the school's storage directories inside the deletion
transaction.

### GAP-04 · Undisclosed cross-border transfer of receipts — **HIGH**

**Where**: `PaymentReceiptScreening::assess()` → `api.anthropic.com`.
**Why it matters**: receipts carry payer names, bank details and amounts.
Schools are not told, there is no processor agreement, and no transfer mechanism
has been established.
**Fix**: disclose (drafted), agree, establish a basis — or reconsider whether
automated screening justifies the transfer.

### GAP-05 · Terms links point to `#`; acceptance not recorded — **HIGH**

**Where**: `resources/views/auth/register.blade.php:291-293`;
`RegisterSchoolRequest` validates `'terms' => ['accepted']` and stores nothing.
**Why it matters**: schools agree to three documents they cannot read, one of
which ("Data Protection Policy") does not exist. No record of what was accepted
or when.
**Fix**: publish, link, add `terms_accepted_at` and `terms_version` to `schools`,
and change the wording to name the documents that exist.

### GAP-06 · API tokens never expire — **HIGH**

**Where**: `config/sanctum.php` — `'expiration' => null`.
**Why it matters**: a token from a lost phone stays valid indefinitely.
**Fix**: set an expiry, prune expired tokens, let users see and revoke sessions.

### GAP-07 · No retention limits on logs holding IP addresses — **HIGH**

**Where**: `page_views`, `audit_logs`, `result_token_access_logs`,
`result_checking_pin_usages`; `routes/console.php` schedules only custom-domain
verification.
**Why it matters**: an indefinitely growing store of personal data with no
justification for its age.
**Fix**: implement the schedule in the retention policy; prune sessions too.

### GAP-08 · Backup position unknown — **HIGH**

**Where**: nothing in the codebase.
**Why it matters**: if hosting provides none, one failure destroys every
school's records. Also blocks completing the retention policy.
**Fix**: confirm, document, encrypt, **test a restore**.

### GAP-09 · No data export — **HIGH**

**Where**: no such route exists.
**Why it matters**: portability cannot be met; a school leaving cannot take its
records; deletion becomes a one-way door.
**Fix**: build a school-level export (records + files).

### GAP-10 · Tenant isolation depends on remembering a call — **MEDIUM**

**Where**: `AuthorizesSchoolOwnership::authorizeSchoolOwnership()`, called
explicitly in each controller.
**Why it matters**: correct today, but a new controller omitting it exposes
another school's records and nothing catches it.
**Fix**: add an automatic global scope as defence in depth; consider a test that
fails when a school-scoped controller lacks the check.

### GAP-11 · No two-factor authentication — **MEDIUM**

**Why it matters**: a password is the only barrier to a school's entire pupil
roll, or — for the ScholarNest Team — to every school.
**Fix**: offer 2FA; consider requiring it for the ScholarNest Team.

### GAP-12 · Blood group collected without a basis — **MEDIUM**

**Where**: `students.blood_group`, `staff.blood_group`.
**Why it matters**: health data about children, no stated purpose, no
special-category condition, no separate access control.
**Fix**: justify and restrict, or **stop collecting it**.

### GAP-13 · No cookie consent mechanism — **MEDIUM**

**Why it matters**: probably defensible given no analytics cookies, but the
third-party font requests disclose visitor IPs, and the NDPA position is
unconfirmed.
**Fix**: confirm with counsel; self-host fonts to remove the question.

### GAP-14 · CSP permits `unsafe-inline` and `unsafe-eval` — **MEDIUM**

**Where**: `SecurityHeaders::contentSecurityPolicy()`. Documented in the code
with its reasoning.
**Fix**: longer-term, move to nonces or hashes.

### GAP-15 · Session cookie defaults — **MEDIUM**

**Where**: `.env.example` — `SESSION_SECURE_COOKIE=false`,
`SESSION_ENCRYPT=false`, `APP_DEBUG=true`.
**Fix**: verify production overrides; consider a boot check that refuses to
start with debug on in production.

### GAP-16 · Free-text `notes` fields — **MEDIUM**

**Where**: `students.notes`, `staff.notes`.
**Why it matters**: invites medical, behavioural and family information with no
extra protection, no retention rule, and no visibility to the person concerned.
**Fix**: guidance in the interface; restrict who can read them.

### GAP-17 · Guardian contact details duplicated on the pupil — **LOW**

**Where**: `students.guardian_name/phone/email` alongside the `guardians` table.
**Why it matters**: two copies drift; correcting one leaves the other wrong,
which frustrates a rectification request.
**Fix**: make the `guardians` relationship authoritative.

### GAP-18 · No documented incident response — **LOW**

**Fix**: write a runbook with a named owner, regulator contacts and templates.

### GAP-19 · No transparency to schools about ScholarNest Team access — **LOW**

**Why it matters**: team access is audit-logged but the school is never told and
cannot review it.
**Fix**: surface an access log to the school.

---

## PART C — REQUIRES LEGAL COUNSEL REVIEW

1. **Children's data** — which rules apply; whether a DPIA is required; what
   parental notification is owed; the basis for holding blood group about a
   child. **Highest priority.**
2. **NDPA compliance** — registration with the NDPC, annual audit filing,
   whether a DPO must be appointed. Nothing has been asserted here.
3. **Controller/processor allocation** — the mapping in the Framework is
   reasoned, not settled. Payment receipt screening and security logging are
   the arguable edges.
4. **Processor obligations and DPA** — none exists with schools; none with
   Anthropic. Normally a legal requirement, not good practice.
5. **International transfers** — a lawful mechanism for the Anthropic transfer;
   whether font requests count; where hosting actually sits.
6. **Limitation of liability** — whether a twelve-month fee cap is reasonable
   for a platform holding children's data, and whether it survives challenge. A
   cap found unreasonable may be disapplied entirely.
7. **Indemnification** — scope and mutuality.
8. **Retention periods** — against Nigerian education and financial
   record-keeping requirements. The periods proposed are drafting suggestions
   only.
9. **Consent requirements** — whether consent is the right basis anywhere, and
   how a school should obtain any consent it needs.
10. **Cookie requirements** under the NDPA specifically.
11. **Payment obligations** — refunds, the consequence of non-payment, and
    whether a manual receipt process creates obligations around timeliness.
12. **Refunds and cancellation** — no refund policy exists. One is needed.
13. **Intellectual property** — whether the restrictions in Terms §10 are
    enforceable, particularly the reverse-engineering limit, which many
    jurisdictions constrain.
14. **Dispute resolution and governing law** — both are placeholders.
15. **Digital signatures** — their legal effect under Nigerian law, what a
    drawn signature on a report card constitutes, and liability for misuse.
16. **Academic records** — statutory obligations on schools that ScholarNest's
    deletion behaviour must not defeat.
17. **Breach notification** — statutory recipients and deadlines.
18. **Visitor conduct reports** — a form that invites the public to report
    named children, with photographs, needs specific attention: defamation
    exposure, the child's rights, and what the school may do with it.
19. **Exclusive plan** — it is advertised in the codebase but cannot be
    purchased. Confirm this creates no misrepresentation exposure.
20. **Third-party and open-source licences** — a licence review of the
    dependency tree was **not** performed and should be.

---

## PART D — NOTE ON WHAT THESE DOCUMENTS CAN DO

The request that prompted this work asked for documents that would prevent
ScholarNest being sued. **No Terms of Service or Privacy Policy can do that**, and
none of these documents was written on that premise.

What they can do:

- record accurately what was agreed, so a dispute starts from a written position
  rather than two recollections;
- allocate responsibility between ScholarNest and schools clearly enough that each
  side knows what it owns;
- demonstrate that data protection was considered — which regulators weigh, and
  which is worth real credit when something goes wrong;
- set honest limits on the service, which are far more likely to hold than
  sweeping ones.

What makes ScholarNest genuinely safer is **Part B**, not Part A. A policy saying
children's photographs are protected does not protect them; moving them to
private storage does. **The documents are worth publishing after the Critical
gaps are closed, and not before** — publishing them first would create a written
record of protections the platform does not have, which is worse than having no
policy at all.
