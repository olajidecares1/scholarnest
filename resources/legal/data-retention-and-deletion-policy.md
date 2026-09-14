---
title: Data Retention & Deletion Policy
version: "1.1"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-13
status: Awaiting legal review
---

# AkademicNest Data Retention & Deletion Policy

**Version 1.1 · Effective [TO BE PROVIDED] · Last updated 13 September 2026**

<!-- internal:start -->
> **Draft.** Section 2 records that most of the schedule in section 3 is not yet
> applied automatically. The two sections are kept separate so that the policy
> does not describe the schedule as already in force. Do not publish without
> reading section 2.
<!-- internal:end -->

---

## 1. Scope

This policy explains how long information in AkademicNest is kept, what happens
when a subscription ends, how deletion works, and what deletion cannot undo.

Retention is decided by two parties:

- **The school** decides how long to keep its pupils' and staff's records. It is
  the controller and may have its own legal obligations.
- **AkademicNest** decides how long to keep the operational records it holds as
  controller, such as audit records, page views and payment records.

## 2. Current position

**At present, AkademicNest does not delete records automatically.**

| | Current position |
| --- | --- |
| Automatic deletion of records | Not yet in place |
| Audit records | Kept without a fixed deletion date |
| Page view records (**including IP addresses**) | Kept without a fixed deletion date |
| Result lookup records (IP address and browser type) | Kept without a fixed deletion date |
| Expired sessions | Not yet removed automatically |
| Recycle bin or undo | **Not available.** Deletion is immediate and permanent |
| Uploaded files after a record is deleted | Some files may remain in storage after the record they belonged to is deleted |
| Bulk export before deletion | **Not yet available** |
| Self service account deletion | **Not available.** Only the AkademicNest Team can delete a school |

Until the schedule in section 3 is applied automatically, records are held for
as long as the subscription is active and until deletion is requested.

## 3. Intended retention schedule

> This schedule is not yet applied automatically. See section 2.

### 3.1 While a subscription is active

| Category | Retention |
| --- | --- |
| Pupil, staff and guardian records | Kept while active, as the school directs |
| Academic records, results, attendance | Kept while active, as the school directs |
| Website content, news, events, gallery | Kept until the school removes it |
| Result tokens | Kept until they expire or are used up |
| Digital signatures | Kept until replaced or removed |
| Payment records and receipts | 7 years from the transaction *(financial record keeping, **[TO BE CONFIRMED]** against Nigerian requirements)* |
| Support tickets | 3 years from closure |

### 3.2 Operational and security records

| Category | Intended retention | Reason |
| --- | --- | --- |
| Audit records | **2 years** | Long enough to investigate an issue, without keeping an indefinite record |
| Page views **with IP address** | **90 days**, after which the IP address is removed and only totals are kept | An IP address is personal data, and usage trends do not need one |
| Result lookup records | **12 months** | Covers a full academic year in case of a dispute about who viewed a result |
| Session records | **Removed once expired** | An expired session serves no purpose |
| Rate limiting counters | A few minutes | Already temporary |
| Application error logs | **90 days** | |

### 3.3 After a subscription ends

| Stage | What happens |
| --- | --- |
| Lapse or non renewal | Administrative access is locked. **Records are kept, not deleted.** |
| Grace period of **90 days** *(proposed)* | The school may renew and continue, or request a copy of its data |
| After the grace period | The school is contacted before any deletion |
| On the school's written instruction | The school's account and records are permanently deleted |

**We do not delete a school's records automatically when a subscription
lapses.** Academic records are often what a school most needs to keep, and a
payment issue is not a reason to lose a pupil's history.

### 3.4 Longer retention

Records may be kept for longer where necessary to comply with a legal
obligation, to establish or defend a legal claim, or where a regulator or court
requires it. Such a legal hold suspends deletion for the records it covers.

## 4. What deletion does

### 4.1 Deleting a pupil, member of staff or guardian

This removes the record together with related records such as attendance,
scores and allocations. **Deletion is immediate and permanent and cannot be
undone.**

A photograph attached to the record may remain in storage after the record has
been deleted.

### 4.2 Deleting a school

A school is deleted by the AkademicNest Team on request, after confirming the
school's name. Deletion removes:

- the school and its pupils, staff, guardians, academic records, website,
  subscriptions and payment records;
- its administrator accounts;
- their active sessions and any pending password reset requests.

A record of the deletion, including a count of what was removed, is kept by
AkademicNest.

Uploaded files, such as photographs, logos, gallery images, signature images,
payment receipts and conduct report attachments, may remain in storage after the
school has been deleted. A school that wants these files removed should say so
in its deletion request.

### 4.3 Deactivation is not deletion

Deactivating a pupil frees a pupil place and prevents sign in, while the record
and its history are kept. This is usually the right choice for a pupil who has
left. Delete a record only when you intend to remove it permanently.

## 5. Requesting deletion

### 5.1 Pupils, parents and staff

**Please contact your school, not AkademicNest.** The school decides what is
recorded about you and can act on your request directly. AkademicNest cannot
delete an individual's records without the school's instruction, because that
would mean changing a school's records against its wishes.

A school may lawfully refuse where it must keep a record, particularly an
academic record.

### 5.2 Schools

Contact AkademicNest at support@akademicanest.com. We will confirm that the
request comes from an authorised person before acting.

**Before requesting deletion, obtain a copy of anything you need.** Deletion is
permanent and cannot be reversed. Because bulk export is not yet available,
please allow time for us to arrange a copy.

### 5.3 Response times

We aim to acknowledge a request within **5 working days** and to act on it within
**30 days**, or to explain why more time is needed. Statutory deadlines take
priority over these targets.

## 6. Backups

<!-- internal:start -->
> **[TO BE PROVIDED]** Whether backups exist, how often they are taken, where
> they are stored, how long they are kept and whether they are encrypted depends
> on the hosting arrangement and must be confirmed by the operator.
>
> **No statement about backups should be published until the arrangement is
> confirmed.** If there are no automated backups, that is a serious operational
> risk, because a hosting failure could result in the loss of every school's
> records.
<!-- internal:end -->

Where backups exist, deleted information may remain in them until they expire.
Restoring a backup to recover one school's data would also restore other data
deleted since, so backups are restored only for disaster recovery.

## 7. Data export

A school cannot currently download all of its records at once from within the
platform.

Report cards and ID cards can be generated and printed, results can be
downloaded with a result token, and the AkademicNest Team can arrange a copy of a
school's records on request. We intend to add a self service export.

## 8. Contact

| | |
| --- | --- |
| Deletion and retention requests | support@akademicanest.com |
| Data protection enquiries | support@akademicanest.com |

<!-- internal:start -->
---

*The retention periods proposed in section 3 have not been checked against
Nigerian education or financial record keeping requirements. They must be
confirmed by a qualified lawyer before publication.*
<!-- internal:end -->
