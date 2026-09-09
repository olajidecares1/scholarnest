# AkademicNest Legal Documents

Six documents, written from an audit of this codebase rather than from a
template. Every factual claim in them was checked against the implementation on
the date below. They still need review by a qualified lawyer — see
`00-LEGAL-REVIEW-AND-GAPS.md`.

## These files are the SEED, not the live copy

> **Editing a file here changes nothing on the site.**

The markdown below seeded the `legal_documents` table when the
`create_legal_documents_table` migration ran. **The table is what schools
read.** Edit the live documents at **AkademicNest Team → Legal Documents**, which
requires the `manage_legal` permission and writes an audit entry for every save.

The files stay in the repository for two reasons: they are what a lawyer reviews
without needing a login, and they are what a fresh install starts from. They are
deliberately *not* re-read on deploy — a file improved in a release must never
silently overwrite an amendment somebody made on purpose.

To change what a fresh install starts with, edit the file. To change what is
live, use the admin screen.

### Notes to counsel inside a document

Anything between `<!-- internal:start -->` and `<!-- internal:end -->` is
stripped before the page is rendered, so open legal questions can live inside
the document without schools reading them. A test asserts no published page
contains the markers or the phrases inside them, so a note added without a fence
fails the build rather than appearing on a public page.

Use it for open points. Do not use it to hide something a school ought to know —
a separate test proves the honest disclosures (the Anthropic transfer, blood
group as health data, the absent cookie banner) are still on the live pages.

| File | Document |
| --- | --- |
| `00-LEGAL-REVIEW-AND-GAPS.md` | Audit summary, implementation gaps, items for counsel |
| `terms-and-conditions.md` | Terms & Conditions / Terms of Service |
| `privacy-policy.md` | Privacy Policy |
| `cookie-policy.md` | Cookie & Browser Storage Policy |
| `data-retention-and-deletion-policy.md` | Data Retention & Deletion Policy |
| `security-and-data-handling-statement.md` | Security & Data Handling Statement |
| `school-data-processing-framework.md` | School Data Processing / Responsibilities Framework |

## Why six and not more

A separate "Data Protection Policy" was **not** written, even though the
registration form names one. An internal data-protection policy governs how
AkademicNest's own staff behave; it is not a public-facing document a school agrees
to, and publishing one as if it were a contract confuses the two. What a school
actually needs to see is covered by the Privacy Policy (what is collected and
why), the Security Statement (how it is protected) and the Processing Framework
(who is responsible for what). **The registration checkbox should be changed to
name the three documents that exist.**

## Audit basis

- Audited: 1 September 2026, against the `dev` branch.
- Method: migrations, models, controllers, middleware, routes, config, services.
- Every "not currently implemented" statement was verified by searching for the
  feature, not assumed from its absence in documentation.

## Before relying on them

1. Read `00-LEGAL-REVIEW-AND-GAPS.md` in full.
2. Fix the **Critical** gaps. Several of these are things the documents would
   otherwise have to describe unfavourably, or would make the documents
   inaccurate the day they were published.
3. Replace every `[TO BE PROVIDED]` placeholder. No address, telephone number,
   email address, registration number or DPO detail was invented.
4. Have a qualified lawyer and a data-protection professional review the set,
   in every jurisdiction where AkademicNest operates.

## A note on what these documents can and cannot do

They cannot make AkademicNest immune from being sued, and nothing in them was
written to imply otherwise. What a well-drafted set of terms does is narrower
and still worth having: it records what was agreed, allocates responsibility
between AkademicNest and the school, sets out the limits of the service honestly,
and demonstrates that the platform's data handling was thought about rather
than improvised. Clauses that overreach — a blanket exclusion of all liability,
a waiver of rights that cannot be waived — tend to be struck out, and a
document containing them is treated with more suspicion, not less.
