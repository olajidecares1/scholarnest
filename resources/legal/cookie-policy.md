---
title: Cookie & Browser Storage Policy
version: "1.0"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-01
status: Awaiting legal review
---

# AkademicNest Cookie & Browser Storage Policy

**Version 1.0 · Effective [TO BE PROVIDED] · Last updated 1 September 2026**

<!-- internal:start -->
> **Draft.** Not yet reviewed by a lawyer. Note in particular that **AkademicNest has
> no cookie consent mechanism**, which section 6 addresses honestly rather than
> quietly.
<!-- internal:end -->

---

## 1. Scope

This policy covers everything AkademicNest stores in a browser: the signed-in school
portals, the public school websites and the AkademicNest marketing site.

It is short for a good reason. **AkademicNest sets no analytics cookies, no
advertising cookies and no third-party tracking cookies.** Every item below is
there to make the application work.

## 2. Cookies

### 2.1 `akademicnest-session`

| | |
| --- | --- |
| **Purpose** | Identifies your browsing session so the server knows who you are between page loads. Signing in is impossible without it. |
| **Type** | Strictly necessary · first-party |
| **Contains** | A session identifier only. The session's contents are held server-side in AkademicNest's database, not in the cookie. |
| **Duration** | 120 minutes by default, refreshed as you use the site. School portal sessions end sooner — see section 4. |
| **Flags** | `HttpOnly` (JavaScript cannot read it) · `SameSite=Lax` · `Secure` in production over HTTPS |
| **Can it be disabled?** | Not while using the Platform. Blocking it makes signing in impossible. |

> The name is derived from the application name, so a differently branded
> deployment will show a different name. On the standard deployment it is
> `akademicnest-session`.

### 2.2 `XSRF-TOKEN`

| | |
| --- | --- |
| **Purpose** | Protects against cross-site request forgery — stops another website from making your browser submit a form to AkademicNest using your signed-in session. |
| **Type** | Strictly necessary · first-party |
| **Contains** | An encrypted anti-forgery token. No personal information. |
| **Duration** | Same as the session cookie |
| **Flags** | Readable by JavaScript **by design**, so the page can attach the token to requests · `SameSite=Lax` · `Secure` in production |
| **Can it be disabled?** | No. Blocking it causes form submissions to be rejected. |

### 2.3 `akademicnest_portal`

| | |
| --- | --- |
| **Purpose** | Remembers **which school and which portal** this browser last signed in to, so that when a session expires you are returned to the right sign-in page instead of a generic one. |
| **Type** | Functional · first-party |
| **Contains** | A school identifier and a portal name, e.g. `12:staff`. **No name, no credentials, no personal details.** |
| **Duration** | 1 year |
| **Flags** | `HttpOnly` |
| **Can it be disabled?** | Yes. Blocking it only means an expired session sends you to the general sign-in page rather than your school's. |

### 2.4 `remember_web_*` (and the equivalent for other portals)

| | |
| --- | --- |
| **Purpose** | Keeps you signed in after the browser is closed, if you tick "remember me". |
| **Type** | Functional · first-party |
| **Contains** | Your account identifier and a long random token, used to re-establish a session. |
| **Duration** | Long-lived — it exists to survive browser restarts |
| **Flags** | `HttpOnly` · `Secure` in production |
| **Can it be disabled?** | **Yes — simply do not tick "remember me".** It is never set otherwise. |

> **Advice.** Do not use "remember me" on a shared or public computer. It keeps
> the browser signed in to a school's records after you have walked away.

## 3. Browser storage (not cookies)

These are stored by the browser and **never sent to AkademicNest's servers**.

| Key | Where | Purpose | Duration |
| --- | --- | --- | --- |
| `theme` | `localStorage` | Whether you chose light or dark appearance | Until cleared |
| `websiteEditTab` | `localStorage` | Which tab of the website builder you had open, so it reopens there | Until cleared |

Both hold a single short value and no personal information. Clearing your site
data removes them; the only effect is that the appearance and the open tab reset
to their defaults.

## 4. Sessions

- The general session lifetime is **120 minutes** of inactivity.
- **School portals are much stricter: three minutes of inactivity ends the
  session.** This applies to School Administrators, staff, pupils and
  parents/guardians, and exists because these accounts hold pupil records and
  are often used on shared devices. Members of the AkademicNest Team are not subject
  to it.
- Sessions are stored **server-side in AkademicNest's database**, not in the browser.
- Signing out ends the session, regenerates the identifier and invalidates it
  server-side.
- Signing in regenerates the session identifier, so a session identifier
  obtained before sign-in cannot be reused afterwards.
- The public school websites are **not** subject to the three-minute timeout. A
  visitor filling in a contact form is not signed in and is not logged out of
  anything.

## 5. Third-party requests

AkademicNest does not embed third-party cookies. Two external services are contacted
by your browser, and while they set no cookies through AkademicNest, **the request
itself discloses your IP address and browser details to them**:

| Service | Where | What it serves |
| --- | --- | --- |
| **Bunny Fonts** (`fonts.bunny.net`) | Signed-in portals and sign-in pages | The interface typeface |
| **Google Fonts** (`fonts.googleapis.com`, `fonts.gstatic.com`) | Public school websites | A typeface the school chose, where that typeface is a Google font |

A school that selects one of the **installed system fonts** rather than a web
font causes no request to Google at all.

<!-- internal:start -->
> **For legal review.** Whether serving fonts from these providers requires
> disclosure or consent has been contested in some jurisdictions. Self-hosting
> the typefaces would remove the question entirely and is recommended.
<!-- internal:end -->

## 6. Consent — stated plainly

**AkademicNest does not currently display a cookie banner or offer cookie controls.**

The reasoning, which should be tested by counsel rather than assumed correct:
every cookie AkademicNest sets is either strictly necessary (session, CSRF) or
functional and set only in response to something the user did (`remember_web_*`
is set only if you tick the box; `akademicnest_portal` is set only when you sign in).
There are no analytics or advertising cookies, which is what consent
requirements are usually aimed at.

That reasoning does not cover everything:

- the font requests in section 5 disclose IP addresses to third parties;
- some regimes require notice of functional cookies even where consent is not
  required;
- the NDPA's position on this should be confirmed rather than inferred.

**We are treating this as an open compliance question, not a settled one.**

## 7. Managing cookies yourself

Every major browser lets you view, block and delete cookies and site data,
usually under Settings → Privacy. Blocking cookies for AkademicNest will prevent you
from signing in.

## 8. Changes

Material changes will be notified through the Platform. The version and date at
the top will change.

## 9. Contact

Questions about this policy: [TO BE PROVIDED].

---

*Every cookie and storage key listed here was found in the codebase. None was
invented, and no cookie found in the codebase was omitted.*
