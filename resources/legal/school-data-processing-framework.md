---
title: School Data Processing & Responsibilities Framework
version: "1.0"
effective_date: "[TO BE PROVIDED]"
last_updated: 2026-09-01
status: Awaiting legal review — intended as the basis for a Data Processing Agreement
---

# School Data Processing & Responsibilities Framework

**Version 1.0 · Effective [TO BE PROVIDED] · Last updated 1 September 2026**

<!-- internal:start -->
> **Draft.** This is written to serve as the basis for a **Data Processing
> Agreement** between ScholarNest and each subscribing school. **No such agreement
> currently exists**, and where ScholarNest acts as a processor one is normally a
> legal requirement. This needs a lawyer's attention before it is offered to
> schools.
<!-- internal:end -->

---

## 1. Why this document exists

A school using ScholarNest and ScholarNest itself both handle personal information about
the same people — pupils, parents, staff. If it is not written down who is
responsible for what, then in practice nobody is, and when something goes wrong
each side assumes the other was handling it.

This document allocates responsibility. It is deliberately **not** an attempt to
push ScholarNest's own obligations onto schools; a processor has direct duties of
its own, and the ones ScholarNest owes are set out in section 5 rather than
disclaimed.

## 2. The roles

| Category | Controller | Processor |
| --- | --- | --- |
| Pupil records | **The school** | ScholarNest |
| Parent/guardian records | **The school** | ScholarNest |
| Staff records | **The school** | ScholarNest |
| Academic records, results, attendance | **The school** | ScholarNest |
| Digital signatures | **The school** | ScholarNest |
| Website content and visitor submissions | **The school** | ScholarNest |
| School Administrator accounts | **ScholarNest** | — |
| Subscription and payment records | **ScholarNest** | — |
| Support tickets | **ScholarNest** | — |
| Audit logs, page views, security logs | **ScholarNest** | — |

**Controller** = decides why and how information is processed.
**Processor** = processes it on the controller's instructions.

### Where this is genuinely arguable

- **Payment receipts.** ScholarNest decides that automated screening happens, chooses
  the provider and sets the criteria. That looks like controller behaviour, even
  though the receipt concerns the school's payment. Treated here as **ScholarNest
  acting as controller** for the screening, which is the more demanding reading.
- **Security logs recording school users' activity.** ScholarNest decides to keep
  them, for its own purposes. Treated as **ScholarNest as controller**.
- **Website content.** A school publishes it, but ScholarNest supplies the template
  and hosts it. Treated as **school as controller** for the content and any
  personal information in it.

<!-- internal:start -->
> **For legal review.** These allocations are reasoned, not settled. Joint
> controllership may be the better characterisation in some areas and would
> carry its own requirements.
<!-- internal:end -->

## 3. What ScholarNest processes, and on what instruction

ScholarNest processes personal information on behalf of a school only to:

1. provide the Platform and the school's Environment;
2. store, display and organise records the school enters;
3. generate documents from them — report cards, ID cards, results;
4. deliver notifications the school configures;
5. operate the school's public website and route visitor submissions to it;
6. back up, secure and monitor the service;
7. investigate and fix faults, including at the school's request;
8. comply with a legal obligation.

**The subscription agreement is the instruction.** ScholarNest will not process a
school's personal information for any other purpose, and specifically:

- **not** to train or improve machine learning models;
- **not** for advertising or marketing to a school's pupils, parents or staff;
- **not** to sell, rent or license it;
- **not** to share it with any other school;
- **not** for ScholarNest's own analytics beyond the operational logging disclosed
  in the Privacy Policy.

If ScholarNest is legally required to process a school's data in some other way, we
will tell the school first unless the law forbids it.

## 4. School responsibilities

A school using ScholarNest is responsible for the following. These are things only
the school can do — they are not ScholarNest obligations relabelled.

### 4.1 Lawful basis and authority
Establishing its own legal basis for recording each category of information, and
having the authority to enter it. This includes deciding whether it needs
parental permission for anything it records or publishes about a pupil.

### 4.2 Accuracy
Everything entered is entered by the school. **ScholarNest does not verify any of
it.** Grades are computed from the scores and grading bands the school
configures; wrong inputs produce wrong report cards, and the school is
responsible for that.

### 4.3 Telling people
Most people in ScholarNest never entered their own information. **Telling pupils,
parents and staff that their information is held, why, and what their rights
are, is the school's job** — ScholarNest has no relationship with them and cannot
do it.

### 4.4 Managing users and access
Creating accounts, assigning roles, and — importantly — **removing access
promptly when someone leaves**. A teacher who has left with an active account
can still see pupil records. ScholarNest cannot know they have left.

### 4.5 Credentials
Keeping administrator credentials secure, not sharing accounts, and instructing
users accordingly. Given that there is **no two-factor authentication**, a
password is the only thing between an outsider and a school's entire pupil roll.
Use a strong, unique one.

### 4.6 Appropriate use
Using pupil information for the school's proper purposes and not for anything
else. Not exporting it to insecure places. Being careful with report cards and
ID cards once printed — the platform's protections end at the printer.

### 4.7 Data subject requests
Handling requests from pupils, parents and staff about their own information,
since the school is the controller. ScholarNest will assist where a school needs
help.

### 4.8 Retention
Deciding how long to keep records, in line with the school's own legal
obligations. **ScholarNest does not delete a school's records on its own initiative**
and does not know a school's retention obligations.

### 4.9 Website content
Everything published on the school's public website, including having the right
to publish any photograph of any pupil or member of staff on it. **A photograph
published on a public website is available to anyone in the world.**

### 4.10 Visitor submissions
Handling contact enquiries and pupil conduct reports appropriately, including
anything a member of the public reports about a child. ScholarNest does not
investigate these and takes no position on them.

### 4.11 Compliance
Complying with education law, data protection law and any other law applicable
to the school. ScholarNest provides a tool; it does not make a school compliant.

## 5. ScholarNest responsibilities

Not disclaimed, and not conditional on the school doing its part.

### 5.1 Processing only on instruction
As set out in section 3.

### 5.2 Security
Implementing appropriate technical and organisational measures — described,
including their current shortcomings, in the *Security & Data Handling
Statement*. **The known gaps in that document are ScholarNest's to fix, not the
school's to work around.**

### 5.3 Confidentiality
Ensuring anyone with access to school data is bound by confidentiality.

### 5.4 Sub-processors
Telling schools who they are (Privacy Policy, section 8) and remaining
responsible for their performance. Currently: the hosting provider, the email
provider, Anthropic (payment receipt screening), and the font providers.

Material changes to this list will be notified in advance.

### 5.5 Assisting the school
Helping, so far as reasonably possible, with data subject requests, impact
assessments and regulator enquiries relating to data ScholarNest processes for the
school.

### 5.6 Breach notification
Notifying the affected school **without undue delay** on becoming aware of a
breach affecting its data, with enough detail for the school to meet its own
obligations.

### 5.7 Deletion and return
On termination, deleting or returning the school's personal data as the school
directs, subject to legal retention obligations and to backups.

> **Currently constrained.** Bulk export is not implemented, so "return" means
> arranging a copy manually. This limits what ScholarNest can honour under 5.7 and
> should be built.

### 5.8 Records and audit
Keeping records of processing, and making available what a school reasonably
needs to verify compliance.

## 6. Individual users

Staff, pupils and parents are responsible for:

- keeping their password confidential and not sharing their account;
- signing out on shared devices, and not using "remember me" on them;
- using the account only for what their school gave it to them for;
- not attempting to reach records they are not entitled to;
- reporting anything that looks wrong rather than exploring it;
- for staff: treating pupil information as confidential, on screen and on paper.

## 7. Responsibility at a glance

| Question | Answer |
| --- | --- |
| Is a pupil record accurate? | **School** |
| Should this pupil be in the system at all? | **School** |
| Who may see a pupil's results? | **School**, within the Platform's options |
| Have parents been told their data is held? | **School** |
| Is the database protected against intrusion? | **ScholarNest** |
| Are passwords stored safely? | **ScholarNest** |
| Can School A see School B's pupils? | **ScholarNest** — prevented in the software |
| Has a leaver's account been disabled? | **School** |
| How long are academic records kept? | **School** decides; **ScholarNest** provides deletion |
| Who answers a parent's access request? | **School** |
| Who notifies a breach of the platform? | **ScholarNest** notifies the school; the **school** notifies its people |
| Who chose the photograph on the public website? | **School** |
| Where do payment receipts go? | **ScholarNest** decides — see Privacy Policy §8.1 |

## 8. Changes

Material changes will be notified to School Administrators before taking effect.

## 9. Contact

| | |
| --- | --- |
| Data protection enquiries | [TO BE PROVIDED] |
| Data Protection Officer | [TO BE PROVIDED] |
| Support | [TO BE PROVIDED] |

---

*This framework should be converted into a signed Data Processing Agreement,
reviewed by a qualified lawyer, before schools are asked to rely on it.*
