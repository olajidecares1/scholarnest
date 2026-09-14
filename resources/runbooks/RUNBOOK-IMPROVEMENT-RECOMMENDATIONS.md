---
title: Runbook Improvement Recommendations
version: 1.0
audit_date: 2026-09-01
status: Internal
---

# Runbook Improvement Recommendations

Written after the runbooks, by comparing them back against the codebase. This is
the part that says what the runbooks could **not** say, and why.

---

## A. Implemented but hard to write a runbook entry for

Features that exist and work, where the troubleshooting path is unclear because
the application does not surface enough to diagnose from.

| Feature | Problem |
| --- | --- |
| **Assignments, Library, Transport, Hostel, Diary, Timetable** | Working modules, but nothing distinguishes "empty because nothing was created" from "empty because this teacher is not assigned". They share one generic entry (§9) rather than six real ones |
| **Co-curricular, Careers, Testimonials, Job postings** | Same. Content modules whose only failure mode is "nothing shows", with no way to tell why |
| **ID cards** | Templates, issuance and QR verification all exist. What is missing is the failure story, no runbook could say what a school sees when a card fails to generate, because nothing reports it |
| **Notices and notice reads** | `SchoolNotice` / `SchoolNoticeRead` track who has read what. Nothing exposes it, so "did the parents see the notice?" is unanswerable |
| **Profile change requests** | A real workflow, staff request, admin approves. Neither side is told when the other acts |
| **Result fee clearance** | The most consequential setting in the product: it decides whether a parent sees a result. It is almost invisible, which is why §4.6 has to spend a paragraph explaining that it exists at all |
| **Retired school result links** | `RetiredSchoolResultLink` exists with no visible workflow. Cannot be documented from the code alone |
| **CBT practice vs CBT tests** | Two features, similar names, different plan gates, near-identical failure modes. Schools will confuse them; the runbook can only warn |

## B. Referred to but not actually there

| Claim | Reality |
| --- | --- |
| **Paystack card payment** | Appears in `PaymentMethod` and in the interface as "Paystack (Card, USSD, Transfer)". **Not integrated.** `StoreTopUpRequest` accepts bank transfer only. A school will try to pay by card and cannot, §14.1 has to explain this |
| **Exclusive plan** | Fully built, gated and priced, and `isAvailableToSubscribe()` returns false. Advertised in the product, unbuyable |
| **`docs/BASIC-PLAN-PORTAL.md`** | Referenced from `config/basic_portal.php`. Confirm it exists and is current before pointing anyone at it |
| **"Data Protection Policy"** | Named on the registration form. Never existed. Already flagged in the legal audit |
| **S3 storage** | Configured in `config/filesystems.php`, not enabled. `FILESYSTEM_DISK=local` |
| **Flutter components** | The brief listed these. **There are none.** There is a versioned JSON API with Sanctum tokens that a mobile client could use, but no Flutter code in this repository |

## C. Problems that need a technical fix, not a runbook entry

Where the runbook is a workaround for something that should not happen.

### C1, Nothing alerts when the queue worker stops, 🟠 High

The single most common operational failure. A school uploads a CBT document, it
sits at "Waiting to start" forever, and **the first anyone knows is a support
ticket.** The heartbeat already exists (`QueueWorkerHealth`); nothing watches it.

*Fix:* alert on a missing heartbeat. This removes §8.1 from the school runbook
almost entirely.

### C2, Class name is a free string, 🟠 High

`class_name` is an unconstrained string on `students`, `teacher_assignments`,
`examinations` and `attendance_records`. "JSS 1A" and "JSS1A" are different
classes, and the result is an empty register, an empty examination and a teacher
who sees nothing, with no error at any point.

This causes entries §2.2, §3.3, §6.2 and §7.1. **Four runbook entries for one
missing foreign key.**

*Fix:* make it a real relationship, or at minimum select-only with normalisation
on save.

### C3, Withheld results do not say why, 🟠 High

A parent whose child owes fees sees a result that is simply not there. No
message, no reason. The school does not know either until someone works it out.

*Fix:* tell the parent the result is withheld and to contact the school. Show the
School Admin which pupils are withheld and why, on the results screen.

### C4, No way to tell "entered" from "published" at a glance, 🟡 Medium

A teacher enters every mark and assumes the job is done. Publishing is a separate
step, and its absence looks identical to the parent.

*Fix:* a per-class indicator, *28 of 31 published, 3 incomplete*.

### C5, Deletion is permanent with no recovery, 🔴 Critical

No soft deletes anywhere. A mis-click on a pupil destroys their academic history
with no undo, and §3.6 can only tell a school to contact AkademicNest and hope.

*Fix:* soft deletes on pupils, staff and guardians, with a 30-day restore window.
This is the highest-value change in this document.

### C6, Capacity refusal does not say what to do, 🟡 Medium

"Student capacity reached" does not mention that deactivating leavers frees
places immediately, so schools either delete pupils, losing records permanently
or pay for capacity they do not need.

*Fix:* show used/total and offer "review inactive pupils" beside the refusal.

### C7, Tenant isolation depends on remembering a call, 🟠 High

`authorizeSchoolOwnership()` in each controller. Correct today; a new controller
that omits it leaks another school's records with nothing to catch it. The
technical runbook has to make this the first check on every cross-tenant report.

*Fix:* an automatic query scope as defence in depth, plus a test that fails when
a school-scoped controller lacks the check.

### C8, Signature attribution is invisible, 🟡 Medium

A signature is recorded against whoever was signed in, correctly and
deliberately. But nothing shows *whose* signature is on a report card, so a
school cannot tell a stale signature from a wrong one without reprinting.

*Fix:* name the signer beside the signature in the admin preview.

### C9, No data export, 🟠 High

A school leaving cannot take its records. §13.4 can only say "contact us and
allow time". It also makes deletion a one-way door with no way to prepare.

### C10, No 2FA, 🟠 High

A password is the only barrier on a School Admin account that reaches every pupil
record in the school, and on a Super Admin account that reaches every school.

## D. Where better error messages would remove a runbook entry

The strongest signal in this audit: **most of the school runbook exists because
the application does not explain itself at the moment of failure.**

| Currently | Should say | Removes |
| --- | --- | --- |
| Empty class list for a teacher | "You have not been assigned to any classes yet. Ask your School Admin." | §2.2 |
| Empty examination | "No pupils are in class *JSS1A*. Your pupils are in *JSS 1A*. Did you mean that?" | §7.1 |
| Result absent for a parent | "This result is being held by your school. Please contact the school office." | §4.6 |
| "Student capacity reached" | "All 200 places are in use. 14 inactive pupils are still holding places, review them, or buy more." | §3.5 |
| CBT stuck at Pending | "Extraction has not started. We have been told. Your document is safe and you do not need to upload it again." | §8.1 |
| Signature missing on a card | "No signature is registered for the class teacher of JSS 2A (*Mrs Adeyemi*)." | §10.5 |
| Score will not save | Name which field and why, "Test score must be 0 to 30" | §4.3 |

Each row above is a runbook entry a school would never need to look up.

## E. Where more logging would help support

| Add | Why |
| --- | --- |
| **Result publish and unpublish events** in `audit_logs` | "Who published this and when?" is currently unanswerable |
| **Fee clearance grants** | It releases a withheld result. It should be as auditable as the withholding |
| **Teacher assignment changes** | The most common cause of "a teacher lost access". No record of who changed what |
| **Capacity changes** | Approved top-ups change entitlement and should be traceable beyond the payment row |
| **Signature registration and replacement** | A signature on a school document deserves a record of when it was drawn |
| **Failed uploads with the rejection reason** | §16.1 is guesswork because nothing records why an upload was refused |
| **Structured request context on 500s** | School, user, route. Currently a stack trace with no tenant context |

## F. What the runbooks deliberately do not do

Stated so nobody adds them later thinking they were forgotten.

- **No commands in the school runbook.** Not one. The application already has a
  precedent for this in `CbtExtractionAvailability::warning($canOperateTheServer)`,
  which defaults to the message with no shell command in it precisely because a
  teacher was once shown `php artisan queue:work`.
- **No instructions to investigate security incidents.** Schools are told to
  stop, preserve and escalate. Reproducing a security problem destroys the
  evidence and can widen the exposure.
- **No "clear all your browser data."** Site-specific clearing only, at level 3
  of 6. Signing someone out of their banking to fix a stale school logo is not a
  fix.
- **No promise that deleted data can be recovered.** Section 13 of the technical
  runbook says the backup position is unknown, and until that is confirmed the
  honest answer to a school is that deletion is permanent.
- **No feature invented to fill a gap.** Where something does not exist,
  export, 2FA, card payment, cookie controls, the runbook says so.

## G. Priority

| | Change | Removes |
| --- | --- | --- |
| 1 | **Soft deletes with a restore window** (C5) | The worst irreversible failure in the product |
| 2 | **Alert on queue worker stop** (C1) | The most common P2 |
| 3 | **Constrain class names** (C2) | Four runbook entries |
| 4 | **Explain withheld results** (C3) | The most confusing entry in the book |
| 5 | **The error messages in section D** | Roughly a third of the school runbook |
| 6 | **Data export** (C9) | A manual support task and a compliance gap |
| 7 | **Automatic tenant scoping** (C7) | A whole class of P1 |
| 8 | **The logging in section E** | Makes support diagnosable rather than deductive |

---

*A runbook is a measure of how much a system fails to explain itself. The aim is
for the next version of this book to be shorter, because the application says
more.*
