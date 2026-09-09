# AkademicNest API v1

The HTTP interface the mobile apps are built against. Read-only in this
version: everything here answers a question a client needs to draw a screen,
and nothing here changes a record.

Base URL: `https://your-domain/api/v1`

---

## What v1 deliberately does not do

Worth knowing before you build against it.

- **No writes.** Submitting homework and marking a register are a second
  conversation about idempotency and offline conflict. v1 does not start it.
- **No School Admin or Super Admin endpoints.** Tokens are issued to students,
  guardians and staff only. A token that can be minted but not used is surface
  for nothing.
- **No staff endpoints yet** beyond sign-in and `/me`. The token type exists so
  a client can authenticate; the register and score-entry surface is not built.
- **No password change.** An account the school has told to change its password
  is refused a token and sent to the web portal — see below.

---

## Signing in

```http
POST /api/v1/tokens
Content-Type: application/json

{
  "role": "student",
  "school_code": "GRN001",
  "login": "GRN001/001",
  "password": "…",
  "device_name": "Ada's phone"
}
```

| Field | Notes |
| --- | --- |
| `role` | `student`, `guardian` or `staff` |
| `school_code` | Always required. There is no domain for the API to recognise a school by, so a client names its school every time. |
| `login` | Admission Number or email (student), email (guardian), Staff Number or email (staff) |
| `device_name` | Shown to the person when they review their own sessions |

**201**

```json
{
  "token": "17|xxxxxxxx…",
  "token_type": "Bearer",
  "expires_at": "2026-10-25T09:14:02+00:00",
  "account": { "role": "student", "profile": { … }, "school": { … } }
}
```

Send it as `Authorization: Bearer <token>` on every later request.

**422** covers every failure: wrong password, wrong school code, deactivated
account, an account that must change its password, and a school whose plan does
not include the app. A bad school code is answered identically to a bad
password on purpose — telling a client which half of the guess was wrong is
telling them half the answer.

The plan gate is the browser's, unchanged: student and guardian accounts need
**Standard or Exclusive**, staff need any active subscription. A Basic school's
parents are not shut out of results by this — they reach them through the
result-token flow, which needs no account at all.

Tokens expire after **60 days**. There is no refresh; the client signs in again.

### Rate limits

- `POST /tokens` — 10 a minute per IP, on top of the per-account, per-school
  limiter the web sign-in already applies. The two answer different questions:
  that one stops somebody guessing at one account, this one stops somebody
  working through many.
- Everything else — 60 a minute, keyed on the **token**. Two devices get a
  budget each, so a retry loop on one does not lock a person out of the other.

### Signing out

```http
DELETE /api/v1/tokens/current   → this device only
DELETE /api/v1/tokens           → every device, for a phone that is gone
```

---

## Every authenticated request

Three checks run before any controller does:

1. the token is real and unexpired;
2. the account **and its school** are still active — re-checked on every
   request, not just at sign-in, because a token lives for weeks and a student
   can be withdrawn inside that window;
3. this *kind* of account belongs at this endpoint. A student's token is a
   perfectly valid token at a guardian's endpoint, and is refused there with
   **403**.

A **401** always means the same thing: sign in again.

---

## `GET /me`

Who this token belongs to. The call a client makes on launch to find out
whether the token it stored is still any good.

---

## Student

All under `/api/v1/student`, and all scoped to the signed-in student.

| Endpoint | Returns |
| --- | --- |
| `GET /results` | Every examination they have a mark in — **and no marks** |
| `POST /results/{examination}` | One full report card, in exchange for its exam token |
| `GET /attendance` | Their own attendance. `from`, `to`, `per_page` |
| `GET /timetable` | Their class's timetable, unpaginated |
| `GET /assignments` | Homework for their class, soonest due first |
| `GET /notices` | Memoranda addressed to students or to everyone |

### Results are two calls, and why

`GET /results` is a table of contents. Each entry carries:

```json
{
  "id": "…", "name": "First Term Examination", "term": "first",
  "session": "2025/2026", "exam_date": "2026-07-14",
  "is_withheld": false, "withheld_reason": null, "requires_exam_token": true
}
```

`is_withheld` is money: the school is holding this result against an unpaid
balance. `requires_exam_token` is permission: even when nothing is withheld, a
portal result opens only against the exam token the school issued for it.

To open one:

```http
POST /api/v1/student/results/{examination}
{ "exam_token": "…" }
```

The web portal remembers an unlocked result in the session. There is no session
here, so the token travels with the request that wants the result. That is a
real difference in feel — a client must ask for the token each time — and it is
the honest way to do it statelessly. The alternative is inventing a second,
longer-lived unlock credential, which is one more thing to steal.

Both gates apply in the same order as the web portal: **fees first**. A token
does not buy a result the school is withholding over money, and checking it the
other way round would spend a token on a result that stays shut anyway.

A token bound to another child is refused *before* it is counted as used, so
trying the wrong one costs nothing.

---

## Guardian

| Endpoint | Returns |
| --- | --- |
| `GET /guardian/children` | The children this guardian is recorded against |
| `GET /guardian/children/{student}` | One of them |
| `GET /guardian/children/{student}/attendance` | That child's attendance |

A child who is not theirs is **404**, not 403 — whether that pupil exists is
not this guardian's business either. Being at the same school is not enough:
the link is what is checked.

A child's own contact details are not included in what a guardian reads. They
get the record, not the child's phone number and address.

---

## Identifiers

Every `id` in this API is a **UUID**. The database's own integer keys are never
serialised — the whole application addresses records this way so an id cannot
be counted up from 1, and an API that leaked the integer would undo that
everywhere at once.

---

## Versioning

The version is the first path segment so it can be the one a client keeps
writing. v2 means a new file under `routes/api/`, not edits to v1 — which is
what versioning is for.
