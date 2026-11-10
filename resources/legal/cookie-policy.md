---
title: Cookie & Browser Storage Policy
version: "1.2"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-13
status: Awaiting legal review
---

# AkademicNest Cookie & Browser Storage Policy

**Version 1.2 · Effective [TO BE PROVIDED] · Last updated 26 September 2026**

<!-- internal:start -->
> **Draft.** Not yet reviewed by a lawyer. Note in particular that AkademicNest
> has no cookie consent mechanism; see section 6.
<!-- internal:end -->

---

## 1. Scope

This policy covers everything AkademicNest stores in your browser across the
signed in school portals, the public school websites and the AkademicNest
website.

**AkademicNest sets no analytics cookies, no advertising cookies and no third
party tracking cookies.** Every item below is needed for the platform to work.

## 2. Cookies

### 2.1 Session cookie (akademicnest-session)

| | |
| --- | --- |
| **Purpose** | Identifies your session so the server knows who you are between page loads. You cannot sign in without it. |
| **Type** | Strictly necessary, first party |
| **Contains** | A session identifier only. Session data is held on AkademicNest's servers, not in the cookie. |
| **Duration** | 120 minutes by default, renewed as you use the site. School portal sessions end sooner; see section 4. |
| **Security** | Cannot be read by scripts on the page, is limited to AkademicNest, and is sent only over secure connections in production |
| **Can it be disabled?** | Not while using the platform. Blocking it prevents you from signing in. |

### 2.2 Security token cookie (XSRF-TOKEN)

| | |
| --- | --- |
| **Purpose** | Protects against cross site request forgery, which is an attempt by another website to make your browser submit a form to AkademicNest using your signed in session. |
| **Type** | Strictly necessary, first party |
| **Contains** | An encrypted security token. No personal information. |
| **Duration** | The same as the session cookie |
| **Security** | Readable by the page so that it can be attached to requests, limited to AkademicNest, and sent only over secure connections in production |
| **Can it be disabled?** | No. Blocking it causes form submissions to be rejected. |

### 2.3 Portal cookie (akademicnest_portal)

| | |
| --- | --- |
| **Purpose** | Remembers **which school and which portal** this browser last signed in to, so that when a session ends you are returned to the correct sign in page. |
| **Type** | Functional, first party |
| **Contains** | A school reference and a portal name. **No name, password or personal details.** |
| **Duration** | 1 year |
| **Security** | Cannot be read by scripts on the page |
| **Can it be disabled?** | Yes. If it is blocked, an ended session takes you to the general sign in page instead of your school's page. |

### 2.4 Remember me cookies (remember_web and equivalents for other portals)

| | |
| --- | --- |
| **Purpose** | Keeps you signed in after the browser is closed, if you tick "remember me". |
| **Type** | Functional, first party |
| **Contains** | Your account reference and a long random token used to restore your session. |
| **Duration** | Long lasting, so that it survives the browser being closed |
| **Security** | Cannot be read by scripts on the page, and is sent only over secure connections in production |
| **Can it be disabled?** | **Yes. Simply do not tick "remember me".** It is never set otherwise. |

> **Advice.** Do not use "remember me" on a shared or public computer, because
> the browser stays signed in to a school's records after you leave.

## 3. Browser storage

These items are stored in your browser and are **never sent to AkademicNest's
servers**.

| Item | Purpose | Duration |
| --- | --- | --- |
| Theme | Whether you chose the light or dark appearance | Until cleared |
| Website editor tab | Which tab of the website builder you last had open | Until cleared |

Each holds a single short value and no personal information. Clearing your site
data removes them, and the appearance and editor tab simply return to their
defaults.

## 4. Sessions

- The general session lifetime is **120 minutes** of inactivity.
- **School portals end the session after three minutes of inactivity.** This
  applies to School Administrators, staff, pupils and parents or guardians,
  because these accounts give access to pupil records and are often used on
  shared devices. Members of the AkademicNest Team are not subject to this
  shorter limit.
- Session data is stored **on AkademicNest's servers**, not in the browser.
- Signing out ends the session and makes its identifier invalid.
- Signing in creates a new session identifier, so an identifier obtained before
  sign in cannot be reused afterwards.
- Public school websites are **not** subject to the three minute limit. A visitor
  filling in a contact form is not signed in.

## 5. Requests to other services

AkademicNest does not use third party cookies. Your browser does contact two
external services. They set no cookies through AkademicNest, but **each request
shares your IP address and browser details with that service**:

| Service | Where | What it provides |
| --- | --- | --- |
| **Bunny Fonts** (fonts.bunny.net) | Signed in portals and sign in pages | The interface typeface |
| **Google Fonts** (fonts.googleapis.com, fonts.gstatic.com) | Public school websites | A typeface chosen by the school, where it is a Google font |

A school that selects one of the **standard system fonts** instead of a web font
causes no request to Google.

<!-- internal:start -->
> **For legal review.** Whether loading fonts from these providers requires
> notice or consent has been disputed in some jurisdictions. Hosting the
> typefaces ourselves would remove the question and is recommended.
<!-- internal:end -->

## 6. Consent

**AkademicNest does not currently display a cookie banner or offer cookie
controls.**

Every cookie AkademicNest sets is either strictly necessary (the session and
security token cookies) or functional and set only in response to something you
did (the remember me cookie is set only if you tick the box, and the portal
cookie is set only when you sign in). There are no analytics or advertising
cookies.

We keep this approach under review, including the font requests described in
section 5 and any notice requirements under the Nigeria Data Protection Act.

## 7. Managing cookies

Every major browser lets you view, block and delete cookies and site data,
usually under Settings, then Privacy. Blocking cookies for AkademicNest will
prevent you from signing in.

## 8. Changes

We will notify you of material changes through the platform. The version number
and date at the top of this policy will change.

## 9. Contact

Questions about this policy: support@akademicanest.com.
