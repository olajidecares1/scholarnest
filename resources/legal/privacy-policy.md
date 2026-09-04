---
title: Privacy Policy
version: "1.0"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-01
status: Awaiting legal review
---

# ScholarNest Privacy Policy

**Version 1.0 · Effective [TO BE PROVIDED] · Last updated 1 September 2026**

<!-- internal:start -->
> **Draft.** Not yet reviewed by a lawyer or data protection professional. Do
> not publish or rely on it until it has been.
<!-- internal:end -->

---

## 1. Who we are

ScholarNest is a school management platform operated by [TO BE PROVIDED — legal
entity name and registration number], of [TO BE PROVIDED — address].

Data protection contact: [TO BE PROVIDED].
Data Protection Officer: [TO BE PROVIDED — *see note in section 20 on whether
one is required*].

This policy explains what personal information passes through ScholarNest, why, and
what happens to it. It covers the school portals, the public school websites and
the mobile API.

## 2. The relationship, in plain terms

Almost all the personal information in ScholarNest was put there by a **school**,
about its own pupils, parents and staff. The school decides what to record, who
may see it, how long to keep it and when to correct it. ScholarNest provides and
runs the system in which that happens.

In data protection language, for that information:

- the **school** is the **controller** — it decides the purposes and means;
- **ScholarNest** is a **processor** — we act on the school's instructions.

For a smaller category — the information we need to run ScholarNest itself as a
business — ScholarNest is the controller. Section 3 says which is which.

<!-- internal:start -->
> **For legal review.** This allocation is our reasoned view of the system as
> built, not a settled legal conclusion, and there are edges where it is
> genuinely arguable. The clearest is automated payment-receipt screening
> (section 8): ScholarNest decides that screening happens, chooses the provider and
> sets the criteria, which points towards ScholarNest being a controller for that
> processing rather than a processor. It should be confirmed by counsel.
> The absence of a signed data processing agreement is noted in section 21.
<!-- internal:end -->

## 3. What we collect, and in which role

### 3.1 School and account information — *ScholarNest is controller*

| Data | Source |
| --- | --- |
| School name, slug, address, phone, email, logo, favicon | School registration and settings |
| School Administrator name, email, username, phone, password (hashed), profile photograph | Registration and profile |
| Subscription plan, status, pupil capacity, dates | Subscription process |
| Payment records, payment method, proof-of-payment documents, verification notes | Payment submission |
| Support tickets and replies | Support requests |

### 3.2 Pupil information — *school is controller, ScholarNest is processor*

Full name, admission number, gender, date of birth, class, house, address,
phone, email, photograph, admission date, free-text notes, active status,
portal password (hashed), and **blood group**.

> **Blood group is health information.** Under the Nigeria Data Protection Act
> and comparable laws it is a special category of personal data attracting
> stricter conditions. It is collected about pupils and about staff. See
> section 7.

### 3.3 Parent and guardian information — *school is controller*

Name, guardian number, email, phone, photograph, portal password (hashed), and
the pupils they are linked to. Pupil records also carry a guardian name, phone
and email entered directly on the pupil.

### 3.4 Staff information — *school is controller*

Name, staff number, gender, date of birth, role, department, qualification,
employment date, **emergency contact name and phone**, address, phone, email,
photograph, **blood group**, free-text notes, portal password (hashed).

### 3.5 Academic information — *school is controller*

Examination records, subjects, scores, computed grades and positions, teacher
and principal remarks, report cards, attendance records, assignments and
submissions, CBT tests, questions, attempts and answers, timetables, teacher
diary entries, co-curricular activities, library loans, transport assignments,
hostel allocations, fee structures, invoices and fee payments.

### 3.6 Result access tokens — *school is controller*

Tokens issued so a parent can look up one pupil's result for one examination.
Stored as a one-way hash plus a separately encrypted copy so the school can
re-display a token it issued. Each use is logged with the time, the outcome,
the **IP address** and the **browser user-agent string**.

### 3.7 Digital signatures — *school is controller*

An image of a signature drawn by a School Administrator or member of staff,
stored against that person's account and printed on documents such as report
cards.

### 3.8 Visitor information from public school websites — *school is controller*

- **Contact enquiries**: name, email, phone, subject, message.
- **Pupil conduct reports**: reporter's name, description, location, and up to
  four attached files (photographs or short video). Contact details are
  deliberately *not* required.

### 3.9 Technical information — *ScholarNest is controller*

| Data | Where | Note |
| --- | --- | --- |
| **IP address**, path, referrer host, traffic source, device type | `page_views` | Recorded for **every** web request, signed in or not |
| IP address, action, description, acting user | `audit_logs` | Administrative actions |
| IP address, user-agent | Result token access logs | Every result lookup |
| IP address | Rate limiting | Held transiently in cache to throttle sign-in and lookups |
| Session identifier and payload | `sessions` table | See section 12 |
| API tokens | Sanctum | For the mobile API |

## 4. Who provides the data

Very little of it is provided by the person it describes. Pupil and staff
records are entered by the school. Parent records are created by the school.
This matters: **the person whose information it is may not know it is in ScholarNest
at all.** Telling them is the school's responsibility, not ours — see the
*School Data Processing Framework*.

## 5. Why we process it

| Purpose | Categories |
| --- | --- |
| Creating and running accounts | Account, pupil, staff, guardian |
| Authentication and access control | Credentials, sessions, tokens |
| School administration | Pupil, staff, class, attendance |
| Academic management and result processing | Academic, examination, result tokens |
| Producing documents (report cards, ID cards) | Pupil, academic, signatures, school branding |
| Communication and notifications | Contact details, notices, messages |
| Operating the public school website | Website content, gallery, news, events |
| Handling visitor enquiries and conduct reports | Visitor submissions |
| Subscription and payment verification | Payment records, receipts |
| Fraud prevention on payments | Receipt documents |
| Security, rate limiting and abuse prevention | IP addresses, session data |
| Audit and accountability | Audit logs |
| Technical support and fault diagnosis | Whatever the fault concerns |
| Understanding platform usage | Page views |
| Legal compliance | As required |

## 6. Legal basis

For information where **ScholarNest is controller**, we rely on:

- **Contract** — running accounts, subscriptions and payments.
- **Legitimate interests** — security, fraud prevention, audit logging, and
  understanding platform usage, balanced against the individual's interests.
- **Legal obligation** — where law requires us to keep or disclose something.

For information where the **school is controller**, the school must identify its
own basis. For a school this will commonly be a public task, a legal obligation,
or the performance of its contract with parents. **ScholarNest does not obtain
consent from pupils or parents and does not rely on consent for this
processing.**

<!-- internal:start -->
> **For legal review.** Bases must be mapped against the NDPA specifically, and
> the condition for processing blood group as health data must be established
> before either the school or ScholarNest can safely continue to collect it.
<!-- internal:end -->

## 7. Children's and pupils' information

ScholarNest is a school platform, so a large part of what it holds is about
children. We treat this as the highest-risk area of the system.

**What is held about a pupil**: name, admission number, gender, date of birth,
photograph, home address, phone, email, class, house, blood group, free-text
notes, guardian contact details, attendance, results, grades, remarks,
assignments, CBT attempts, library loans, transport, hostel allocation, fees,
and any misconduct report a member of the public has filed about them.

**Who enters it**: the school — administrators and staff.

**Who can see it**:

| Party | Access |
| --- | --- |
| School Administrator | All pupil records in that school |
| Staff | Records within their school, scoped by role and assignment |
| Pupil | Their own results, attendance, assignments, notices and timetable |
| Parent/guardian | Their linked children's records (Standard and above) |
| Anyone with a result token | One pupil's result for one examination, limited uses |
| ScholarNest Team | Technically able to reach it — see section 10 |
| Another school | **Never.** Enforced in the application |

**Who can change it**: school administrators and, within limits, staff. Pupils
and parents cannot edit their own records directly; a change request mechanism
exists for portal profile changes, which the school approves.

**Who can download it**: schools can generate and print report cards and ID
cards. Result tokens allow a result to be downloaded.

**Retention**: see the *Data Retention & Deletion Policy*.

**How it is protected**: see section 11 and the *Security & Data Handling
Statement* — **including its statement that pupil photographs and signature
images are currently stored at publicly reachable addresses**, which is the most
significant open issue affecting children's data on the platform.

**Consent.** ScholarNest does not obtain parental consent. The school is responsible
for having the authority and any required permission to record its pupils'
information and to publish any photograph on its public website.

<!-- internal:start -->
> **For legal review — highest priority.** Which child-data rules apply, whether
> a data protection impact assessment is required, what parental notification is
> owed, and on what basis blood group may be held about a child.
<!-- internal:end -->

## 8. Third parties

ScholarNest uses few external services. Those it does use are:

### 8.1 Anthropic (payment receipt screening) — *personal data leaves ScholarNest*

When a school uploads proof of payment, **the receipt image or document is sent
to Anthropic's API (`api.anthropic.com`) to be checked automatically** before a
person reviews it.

- **What is sent**: the receipt file itself and the school's name. A receipt
  commonly shows the payer's name, bank, account details, amount and date.
- **Why**: to turn away obvious non-receipts before they reach manual review.
- **Where**: Anthropic is based outside Nigeria. **This is a cross-border
  transfer of personal data.**
- **Control**: once sent, the data is subject to Anthropic's own terms and
  protections. ScholarNest does not control what happens to it there.
- If the service is unavailable, the upload proceeds and is reviewed by a person
  with a note that the automatic check did not run.

<!-- internal:start -->
> **For legal review — high priority.** This transfer needs a lawful transfer
> mechanism, a processor agreement, and disclosure to schools. Schools are not
> currently told it happens, and cannot opt out of it while paying by transfer.
<!-- internal:end -->

### 8.2 Bunny Fonts (`fonts.bunny.net`)

Serves the typeface used across the signed-in portals. **The visitor's browser
requests the font directly**, which discloses their IP address and user-agent to
that provider. Bunny Fonts is used specifically because it is marketed as not
tracking users; we have not independently verified that claim.

### 8.3 Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`)

Serves typefaces on **public school websites**, where a school has chosen one.
The visitor's browser requests these directly, disclosing their IP address to
Google. This applies only to Google-hosted typefaces; a school that selects one
of the installed system fonts causes no such request.

### 8.4 Email delivery

Notifications may be sent by email through a mail provider. **The provider is
configured per deployment and is not fixed in the codebase**; the operator must
name it here before publication: [TO BE PROVIDED].

### 8.5 Hosting

The Platform and its database run on [TO BE PROVIDED — hosting provider and
region]. Object storage (Amazon S3) is *supported* by the configuration but is
**not enabled by default**; files are written to the application server's own
disk unless the operator changes this.

### 8.6 What we do **not** use

Verified absent from the codebase:

- **No analytics service.** No Google Analytics, Tag Manager, Meta pixel,
  Hotjar, Segment or similar. Page views are recorded in ScholarNest's own database.
- **No advertising networks.**
- **No card processor.** Paystack appears as an option in the interface but is
  **not integrated**; no card details reach ScholarNest.
- **No CDN** for application assets.
- **We do not sell personal information, and we do not share it between
  schools.**

## 9. Sharing

Beyond section 8, personal information is disclosed only:

- to the users a school has authorised, within that school;
- to a holder of a valid result token, for the single result it was issued for;
- to the ScholarNest Team for support, security and payment verification;
- where required by law, or to establish or defend legal claims;
- to a successor entity on a sale or reorganisation, on notice.

**One school can never see another school's data.** Every request is checked
against the acting user's school, and the check refuses rather than filters.

## 10. ScholarNest Team access

Members of the ScholarNest Team with the appropriate role can, through the
administrative interface, reach school records and act on them — including
deleting a school and everything in it. Administrative actions are recorded in
an audit log with the actor, the action and the IP address.

**Honest limitations**: access is governed by role permissions, but there is no
technical restriction that prevents a sufficiently privileged team member from
viewing a school's records, no requirement to state a reason, and no separate
alert to the school when it happens.

## 11. How information is stored and protected

Summarised here; stated fully in the *Security & Data Handling Statement*.

- **Database**: MySQL.
- **Passwords**: hashed with bcrypt (12 rounds). Never stored or recoverable in
  readable form. Minimum eight characters with mixed case, a number and a
  symbol.
- **Result tokens**: stored as a one-way hash plus a separately encrypted copy.
- **Files**: payment receipts and conduct-report attachments are on a
  **private** disk. **Photographs of pupils, staff and guardians, school logos,
  gallery images and signature images are on a publicly readable disk** at
  unguessable but unprotected addresses.
- **Transport**: HTTPS enforced in production, with HSTS.
- **In production, the database itself is not encrypted at the application
  level** beyond the specific fields named above.

## 12. Sessions and cookies

Covered in full by the *Cookie & Browser Storage Policy*. In summary: a session
cookie is required to sign in; school portal sessions end after **three minutes
of inactivity**; a "remember me" option is available; no analytics or
advertising cookies are set; **there is currently no cookie consent banner**.

## 13. Retention

Covered in full by the *Data Retention & Deletion Policy*.

**Stated plainly**: the Platform does not currently delete anything
automatically. Audit logs, page-view records (with IP addresses), result token
access logs and expired sessions accumulate indefinitely, and files remain on
disk after their database records are deleted. This is a gap we intend to close;
it is described honestly here rather than dressed up as a retention schedule
that is not enforced.

## 14. Deletion and other rights

Depending on jurisdiction, an individual may have the right to access, correct,
erase, restrict, object to, or receive a portable copy of their personal
information.

**Where to direct a request:**

- **Pupils, parents and staff** should contact **their school**, which decides
  what is recorded about them and is the controller for it. ScholarNest cannot
  action such a request on its own.
- **School Administrators** may contact ScholarNest about their own account.
- If a school asks us to help with a request it has received, we will.

**Limits we will not pretend away:**

- A school may be required by education law or its own obligations to keep
  academic records, and can refuse deletion on that basis.
- Deletion of a school Environment is permanent and cannot be reversed by us.
- **Bulk export is not currently available as a feature.** A school wanting a
  copy of its records should contact us to arrange it.

You may also complain to a data protection authority — in Nigeria, the Nigeria
Data Protection Commission.

## 15. International transfers

Personal information is transferred outside Nigeria in one confirmed case:
**payment receipts sent to Anthropic's API** (section 8.1). Font requests to
Bunny and Google disclose visitors' IP addresses to servers outside Nigeria.

Where the platform is hosted, and therefore whether the database itself sits
outside Nigeria, depends on the deployment: [TO BE PROVIDED].

<!-- internal:start -->
> **For legal review.** Each transfer needs a lawful basis under the NDPA's
> transfer provisions. This has not been established.
<!-- internal:end -->

## 16. Automated decision-making

Payment receipts are screened automatically (section 8.1). The screening can
**reject** an upload — telling the school the document does not look like a
receipt, or that the amount falls short — but it can never **approve** one. A
person always reviews before a subscription is activated, and a school whose
receipt is rejected can contact us.

No other automated decision-making with legal or similarly significant effect
takes place. Grades and positions are calculated arithmetically from values and
grading bands the school itself configures; that is computation, not profiling.

## 17. Security incidents

Our approach is set out in the *Security & Data Handling Statement*. In summary:
we will investigate and contain, assess who is affected, notify affected schools
without undue delay, and notify a regulator where legally required.

**We do not commit to a fixed notification time in this policy**, because a
commitment made before an incident that cannot be honoured during one is worse
than none. Statutory deadlines apply regardless of what is written here.

<!-- internal:start -->
> **For legal review.** A documented incident response procedure with defined
> roles and regulator timescales does not yet exist and should.
<!-- internal:end -->

## 18. Changes to this policy

Material changes will be notified through the Platform or by email to School
Administrators. The version number and date at the top will change.

## 19. Contact

| | |
| --- | --- |
| Operator | [TO BE PROVIDED] |
| Address | [TO BE PROVIDED] |
| Privacy enquiries | [TO BE PROVIDED] |
| Data Protection Officer | [TO BE PROVIDED] |
| Supervisory authority (Nigeria) | Nigeria Data Protection Commission |

## 20. Registration and DPO

<!-- internal:start -->
> **For legal review.** Whether ScholarNest is required to register with the Nigeria
> Data Protection Commission, to file an annual audit return, or to appoint a
> Data Protection Officer depends on how much personal data it processes and on
> current NDPC thresholds and guidance. Given that the platform holds children's
> records including health data, this should be assessed before publication
> rather than after. Nothing has been asserted here about registration status.
<!-- internal:end -->

## 21. Processing agreements

<!-- internal:start -->
> **For legal review.** No data processing agreement is currently offered to
> schools, and none is in place with Anthropic for the receipt screening
> described in section 8.1. Where ScholarNest acts as a processor, a written
> agreement is normally a legal requirement rather than good practice. See the
> *School Data Processing Framework*, which is drafted to serve as the basis for
> one.
<!-- internal:end -->

---

*This policy describes the platform as it was implemented on 1 September 2026.
It should be reviewed and approved by a qualified data protection professional
before publication, particularly because ScholarNest processes educational records,
children's personal data and health information.*
