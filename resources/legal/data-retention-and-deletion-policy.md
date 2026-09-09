---
title: Data Retention & Deletion Policy
version: "1.0"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-01
status: Awaiting legal review
---

# AkademicNest Data Retention & Deletion Policy

**Version 1.0 · Effective [TO BE PROVIDED] · Last updated 1 September 2026**

<!-- internal:start -->
> **Draft.** Section 2 records that most of the schedule in section 3 is **not
> yet enforced by the software**. Publishing section 3 as though it were would
> be a false statement about the platform, so the two are kept apart
> deliberately. Do not publish without reading section 2.
<!-- internal:end -->

---

## 1. Scope

How long information in AkademicNest is kept, what happens when a subscription ends,
and how deletion works — including what deletion cannot undo.

Two different parties decide retention:

- **The school** decides how long to keep its pupils' and staff's records. It is
  the controller and may have its own legal obligations.
- **AkademicNest** decides how long to keep the operational records it holds as
  controller — audit logs, page views, payment records.

## 2. Current state — read this first

**As implemented today, AkademicNest deletes almost nothing automatically.**

| | Status |
| --- | --- |
| Scheduled pruning of any table | **None.** The only scheduled task is custom-domain verification |
| Audit logs | Kept indefinitely |
| Page-view records (**including IP addresses**) | Kept indefinitely, written on every request |
| Result token access logs (IP + user-agent) | Kept indefinitely |
| Expired sessions | Not pruned |
| Soft deletes / recycle bin | **Not implemented anywhere.** Deletion is immediate and permanent |
| Uploaded files after a record is deleted | **Frequently left on disk.** Some paths delete the file; deleting a whole school does not |
| Bulk export before deletion | **Not implemented** |
| Self-service account deletion | **Not implemented.** Only the AkademicNest Team can delete a school |

Section 3 is therefore the **intended** schedule. It requires the work in the
gap report to become true. Until then, AkademicNest should state its retention as
"held for as long as the subscription is active and until deletion is
requested", which is accurate.

## 3. Intended retention schedule

> Not yet enforced automatically. See section 2.

### 3.1 While a subscription is active

| Category | Retention |
| --- | --- |
| Pupil, staff and guardian records | Kept while active, as the school directs |
| Academic records, results, attendance | Kept while active, as the school directs |
| Website content, news, events, gallery | Kept until the school removes it |
| Result tokens | Kept until they expire or are exhausted |
| Digital signatures | Kept until replaced or removed |
| Payment records and receipts | 7 years from the transaction *(financial record-keeping — **[TO BE CONFIRMED]** against Nigerian requirements)* |
| Support tickets | 3 years from closure |

### 3.2 Operational and security records

| Category | Proposed retention | Why |
| --- | --- | --- |
| Audit logs | **2 years** | Long enough to investigate; short enough not to be an indefinite record of who did what |
| Page views **with IP address** | **90 days**, then aggregate and discard the IP | An IP address is personal data. Usage trends do not need one |
| Result token access logs | **12 months** | Covers a full academic cycle for disputes about who viewed a result |
| Session records | **Pruned once expired** | An expired session has no purpose |
| Rate-limit counters | Minutes — already transient | |
| Application error logs | **90 days** | |

### 3.3 After a subscription ends

| Stage | What happens |
| --- | --- |
| Lapse or non-renewal | Administrative access locked. **Records are retained, not deleted.** |
| Grace period — **90 days** *(proposed)* | The school may renew and resume, or request a copy of its data |
| After the grace period | The school is contacted before any deletion |
| On the school's written instruction | The Environment and its records are permanently deleted |

**We do not delete a school's records automatically when a subscription lapses.**
Academic records are often the very thing a school most needs back, and a
payment problem is a poor reason to destroy a pupil's history.

### 3.4 Longer retention

Records may be kept longer where necessary to comply with a legal obligation, to
establish or defend a legal claim, or where a regulator or court requires it
(a legal hold). A hold suspends deletion for the records it covers.

## 4. What deletion actually does today

### 4.1 Deleting a pupil, member of staff or guardian

Removes the record and, through database cascades, dependent records such as
attendance, scores and allocations. **Immediate and permanent — there is no
undo.**

Photographs are removed on some paths and not on others. Where a photograph is
not explicitly deleted it **remains on disk** even though the record is gone.

### 4.2 Deleting a school

Carried out by the AkademicNest Team on request, and requires typing the school's
name to confirm. It removes:

- the school and, by cascade, its pupils, staff, guardians, academic records,
  website, subscriptions and payment records;
- its administrator accounts;
- their active sessions and any pending password reset tokens.

An audit entry recording the deletion and a count of what was removed is written
**before** the deletion, and survives it.

**What is not removed**: files already written to disk — pupil photographs, staff
photographs, logos, gallery images, hero images, signature images, payment
receipts and conduct-report attachments. **These remain on the server after the
school is gone.** This is the most serious deletion gap in the platform.

### 4.3 Deactivation is not deletion

Deactivating a pupil frees a licence place and prevents sign-in. The record and
its history remain, which is usually what a school wants for a leaver. Say
"delete" only if you mean it.

## 5. Requesting deletion

### 5.1 A pupil, parent or member of staff

**Contact your school, not AkademicNest.** The school decides what is recorded about
you and can act on the request directly. AkademicNest cannot delete an individual's
records at their own request without the school's instruction, because doing so
would mean altering a school's records against its wishes.

A school may lawfully refuse where it must keep the record — academic records in
particular.

### 5.2 A school

Contact AkademicNest at [TO BE PROVIDED]. We will verify that the request comes from
an authorised person before acting.

**Before requesting deletion, obtain a copy of anything you need.** Deletion is
permanent and AkademicNest cannot restore it. Because bulk export is not yet a
feature, allow time to arrange a copy.

### 5.3 Response times

We aim to acknowledge within **5 working days** and to act within **30 days**,
or to explain why longer is needed. Statutory deadlines take precedence over
these targets.

## 6. Backups

<!-- internal:start -->
> **[TO BE PROVIDED]** — the codebase contains no backup implementation, so
> whether backups exist, how often they are taken, where they are stored, how
> long they are kept and whether they are encrypted **depends entirely on the
> hosting arrangement** and cannot be stated from the code.
>
> **No claim about backups should be published until the operator confirms the
> arrangement.** If there are no automated backups, that is a critical
> operational risk in its own right — a hosting failure would destroy every
> school's records with no recovery.
<!-- internal:end -->

Where backups exist, deleted data may persist in them until they expire.
Restoring a backup to recover one school's data would also restore other data
deleted since, so restores are done only for genuine disaster recovery.

## 7. Data export

**Not currently implemented — recommended.** A school cannot presently download
its records in bulk from within the Platform.

What does exist: report cards and ID cards can be generated and printed, results
can be downloaded through a result token, and the AkademicNest Team can export a
subscriptions list. None of these amounts to a school getting its data out.

A self-service export should be built. It matters for portability rights, and it
matters practically — a school should never be in a position where leaving
AkademicNest means losing its records.

## 8. Contact

| | |
| --- | --- |
| Deletion and retention requests | [TO BE PROVIDED] |
| Data protection enquiries | [TO BE PROVIDED] |

---

*The retention periods proposed in section 3 are drafting suggestions and have
not been checked against Nigerian education record-keeping or financial
record-keeping requirements. They must be confirmed by a qualified lawyer before
publication.*
