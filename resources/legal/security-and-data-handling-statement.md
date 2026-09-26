---
title: Security & Data Handling Statement
version: "1.2"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-13
status: Awaiting legal review
---

# AkademicNest Security & Data Handling Statement

**Version 1.2 · Effective [TO BE PROVIDED] · Last updated 26 September 2026**

<!-- internal:start -->
> **Draft.** The list of improvements under "Remediation plan" at the end of
> this document is for the AkademicNest Team only and is not shown on the
> published page. Those items should be fixed, not simply left out.
<!-- internal:end -->

---

## 1. Purpose

This statement describes how AkademicNest protects the information it holds.

## 2. Authentication

**Passwords**

- Passwords are stored using strong one way hashing. They are never stored in
  readable form and cannot be recovered by anyone, including the AkademicNest
  Team.
- Passwords must be at least **8 characters long, with upper and lower case
  letters, a number and a symbol**. This applies to registration, password
  changes and password resets.
- When a school creates an account with a temporary password, the account
  holder **must** set a new password before the portal can be used for anything
  else.
- When a password is changed, the account holder is notified, including the IP
  address the change was made from.

**Separate accounts for each portal**

Pupils, parents and guardians, staff and administrators sign in through
**separate portals with separate account records**. A pupil's login cannot be
used to sign in to a staff portal.

**Limits on repeated attempts**

Sign in attempts are limited by account, by school and by IP address on every
portal. Result token lookups are limited separately. The mobile application is
limited to 60 requests a minute for each device.

## 3. Access control and school separation

One school can never access another school's records. This is enforced on
AkademicNest's servers: every request is checked against **the school of the
signed in user**, and access is refused where they do not match. A school
reference submitted with a request is never trusted on its own.

The same principle applies throughout the platform:

- **Signatures** are always recorded against the signed in account. A signature
  cannot be attributed to another person by changing a form or an address.
- **Result tokens** are linked to one pupil and one examination when they are
  created, and that link is checked against the issuing school each time.
- **Visitor submissions** are sent to the school whose page they came from, based
  on the address of the page rather than anything in the form.
- **Plan features** are enforced on the server. A feature that is not included in
  a school's plan is refused, even if its address is typed in directly.

## 4. Connection and browser protections

- **HTTPS is enforced in production.** Plain HTTP requests are redirected, and
  browsers are instructed to use only secure connections for AkademicNest.
- A **Content Security Policy** is sent with every page. It prevents plugins,
  prevents forms from sending data to other websites, and prevents AkademicNest
  pages from being embedded in other sites, which protects against
  clickjacking.
- Further browser security headers prevent file type confusion and limit the
  information shared with other websites.
- Browser access to the camera, microphone, location, payment and USB features
  is switched off for AkademicNest pages.

## 5. Protection against common attacks

| Attack | Protection |
| --- | --- |
| **SQL injection** | Database queries use parameter binding, so user input is never treated as part of a query. |
| **Cross site scripting** | Content shown on pages is escaped by default. |
| **Cross site request forgery** | Every form submission and change request is checked for a valid security token. |
| **Session fixation** | A new session identifier is created at sign in. |
| **Automated form abuse** | Public forms include hidden checks for automated submissions and are rate limited. |
| **Mass assignment** | Only approved fields can be set from a request. |

## 6. File uploads

- **Stored file names are chosen by AkademicNest** using random identifiers. The
  original file name is not used.
- **File types are identified from the file's content**, not its name, and are
  checked against a list of permitted types. A file cannot be stored as
  executable code.
- **SVG images are not accepted**, because they can contain scripts.
- Size and number limits apply to every upload.
- **Location and camera information (EXIF data) is removed** from uploaded
  images before they are stored.

**Private storage**, which cannot be reached by an address and is served only to
authorised users:

- payment receipts;
- conduct report attachments;
- job application CVs and supporting documents.

**Public storage**, which can be opened by anyone who has the file's address:

- **pupil, staff and guardian photographs**;
- **signature images**;
- school logos, favicons, facility images, news images, gallery images, hero
  slides, ID card templates and test question images.

Public file addresses are long random identifiers that cannot be guessed.
However, anyone who has the address of a public file can open it, so these
addresses should not be shared.

## 7. Result tokens

Result tokens are designed on the assumption that they may be passed on.

- Tokens are stored in a protected form, with a separately **encrypted** copy so
  that a school can display a token it issued again.
- **Each token is linked to one pupil and one examination when it is created.**
- **Each token can be used a limited number of times** (five by default, and
  configurable), so a token that is passed around stops working.
- Tokens can be given an expiry date.
- **Every attempt is recorded** with the time, the outcome, the IP address and
  the browser type, so a school can see whether and how often a token was used.
- Lookups are rate limited by school and by IP address.

**If a token is compromised**, it gives access to one pupil's result for one
examination and nothing else. The school can view the access record and revoke
the token. A token gives no access to any portal, any other pupil or any other
examination.

## 8. Records and monitoring

- **Administrative actions** are recorded with the person responsible, a
  description, the affected item and the IP address. When a school is deleted,
  the record of that deletion is kept.
- **Sign ins** and failed sign in attempts are recorded.
- **Password resets** are recorded, and the account holder is notified.
- **Result token use** is recorded in full.
- **Page views** record the page, the referring website, the traffic source, the
  device type and the **IP address**.

Detailed error information is never shown to visitors in production.

## 9. Sessions

- Session data is stored **on AkademicNest's servers**, not in the browser.
- School portals sign users out after **three minutes of inactivity**. This
  applies to administrators, staff, pupils and parents alike, because these
  accounts give access to pupil records and are often used on shared devices.
- The inactivity limit does **not** apply to public school websites, so visitors
  do not lose a message they are writing.
- Signing out ends the session on the server.
- Deleting a school ends its administrators' active sessions and cancels any
  pending password reset requests.

## 10. Security incidents

If personal information is accessed without authorisation, exposed or lost, or
a provider we rely on suffers a breach, we will:

1. **Contain** the incident by stopping the access and revoking affected
   credentials and tokens.
2. **Investigate** what happened, what was affected and who was involved.
3. **Assess** the risk to the people concerned, which may be significant where
   pupils' information is involved.
4. **Notify affected schools without undue delay**, explaining what we know, what
   we are doing, and what they should do, including anything they need to pass
   on to parents or staff.
5. **Notify the regulator** where the law requires it. In Nigeria this is the
   Nigeria Data Protection Commission, and statutory deadlines apply.
6. **Record** the incident and take steps to prevent it from happening again.

To report a vulnerability or a suspected incident, contact
**support@akademicanest.com**. Please report issues rather than investigating them
yourself. We will not take action against anyone who reports a vulnerability in
good faith.

## 11. Contact

| | |
| --- | --- |
| Security reports | support@akademicanest.com |
| Data protection enquiries | support@akademicanest.com |

<!-- internal:start -->
## Remediation plan (AkademicNest Team only)

> **Not published.** Improvements identified for the platform, in order of
> priority. Update this list as items are completed.
>
> **Critical: pupil photographs are at public addresses.** Photographs of
> children, staff and guardians are in public storage. The addresses cannot be
> guessed, but anyone who obtains one can open the image. *Fix:* move
> photographs to private storage and serve them through a check of the viewer's
> school and role, as conduct report attachments already are.
>
> **Critical: signature images are at public addresses.** A signature taken from
> a public address could be reproduced on another document. *Fix:* private
> storage, served only as part of an authorised document.
>
> **Critical: deleting a school may leave its files in storage.** *Fix:* delete
> the school's stored files as part of the deletion.
>
> **High: the accepted version of the Terms is not recorded.** The documents are
> now published and linked from registration, but the version a school accepted,
> and when, is not stored. *Fix:* store the accepted version and time against the
> school.
>
> **High: mobile access tokens do not expire.** A token from a lost phone stays
> valid until revoked manually. *Fix:* add an expiry, remove expired tokens, and
> let users see and revoke their own sessions.
>
> **High: no retention limits on records containing IP addresses.** *Fix:*
> implement the schedule in the Data Retention & Deletion Policy.
>
> **High: backup position not documented.** *Fix:* confirm, document, encrypt,
> and test a restore.
>
> **Medium: school separation relies on a check in each controller.** *Fix:* add
> an automatic query scope as a second layer, and a test that fails when a school
> scoped controller lacks the check.
>
> **Medium: no two factor authentication.** *Fix:* offer it, and consider
> requiring it for the AkademicNest Team.
>
> **Medium: the Content Security Policy allows inline and evaluated scripts.**
> *Fix:* move to nonces or hashes over time.
>
> **Medium: blood group is collected without an established purpose.** *Fix:*
> establish why it is needed and restrict who can see it, or stop collecting it.
>
> **Medium: session encryption is off by default and the secure cookie setting
> must be enabled in production.** *Fix:* confirm both in production.
>
> **Medium: free text notes on pupils and staff.** These can end up holding
> sensitive information. *Fix:* add guidance on what not to record and restrict
> who can see them.
>
> **Low: no written incident response procedure.** *Fix:* write one, with a named
> owner, regulator contacts and notification templates.
<!-- internal:end -->
