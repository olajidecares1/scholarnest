# Basic-plan student licences

How a Basic school's student capacity is bought, approved and enforced.

Applies to the **Basic plan only**. Standard and Exclusive are flat-fee and
uncapped; none of this touches them.

---

## The rule

> A Basic school may have exactly as many students as the Super Admin has
> allocated after verifying a payment. A receipt is a **request**, never a
> grant.

The workflow, in order, with no shortcuts:

```
School pays
   -> School submits receipt          (status: pending, capacity unchanged)
   -> Super Admin views the receipt
   -> Super Admin enters the number of licences the payment covers
   -> System applies that number
   -> School can add students within the new limit
```

---

## 1. Pricing

Per-student, per-term, held in `plans.price_per_student_per_term`.

**Editable by the Super Admin** at *Plan Pricing*. It is not a constant in the
source, so changing it needs no developer and no deployment.

Changing the price never rewrites a payment already submitted. Each top-up
snapshots the price it was quoted at in `subscription_top_ups.price_per_student`,
so a school is always charged what it was shown. A new price applies only to
requests made after the change.

At NGN 500:

| Students | Payment |
| -------- | ------- |
| 100 | NGN 50,000 |
| 300 | NGN 150,000 |
| 50 (top-up) | NGN 25,000 |

---

## 2. The school submits a request

*School Admin → Students → Need more licences*, or the **Add More Students**
button that appears when capacity runs low.

The school enters how many students it wants and uploads its receipt. The
amount owed is calculated from the plan price and shown before submitting.

**Nothing about the school's capacity changes.** The row is created with status
`pending_verification`, the Super Admins are notified, and an audit entry is
written.

Receipts are stored on the **private** disk, never in public storage. A receipt
carries bank details and reference numbers; anything under `public/storage` is
readable by anyone who can guess a filename.

---

## 3. The Super Admin decides

*Super Admin → Subscriptions → Student Top-ups*.

Each pending row shows what was requested, the amount, the price per student,
and a **View receipt** link that streams the file through the application
behind the `super_admin` middleware.

Approving requires **typing the number of licences to allocate**. This is the
heart of the rule, and it is why there is a field rather than just a button:

- The number entered is what gets applied, never the number the school asked
  for.
- The two are allowed to differ. A school that requested 50 but paid for 40
  receives 40.
- The field has no default. Approving is a deliberate entry, not a click that
  silently accepts whatever the school typed.

The request is kept alongside the decision, so the two can always be compared.

---

## 4. Enforcement

`App\Services\StudentLicenceAllocation` is the single authority. Everything that
could push a school over its allocation goes through `withCapacity()`.

Three ways the limit could be beaten, and what stops each:

### The form

Blocked in the controller, not the page. Posting straight at the route with no
interface involved is refused exactly the same way.

### Two requests at once

Counting students and then creating one is two statements. Two simultaneous
submissions, a double-click is enough, could both count 99 against a limit of
100 and both insert, leaving 101.

`withCapacity()` counts and creates inside one transaction, holding
`lockForUpdate()` on that school's students, so concurrent attempts queue rather
than racing.

### Deactivate, admit, reactivate

A school at 100/100 could deactivate a student, admit a replacement, then
reactivate the first, 101 active students with every individual step looking
legitimate.

Reactivating therefore goes through the same check as creating. Deactivating
always works, because it releases a licence.

> Licences are counted against **active** students. Deactivating a student who
> has left frees their licence, which is what makes a per-student price fair
> across a year.

---

## 5. What the school sees

The Students page shows **used / allocated** and **remaining**.

| State | What appears |
| ----- | ------------ |
| Normal | Green figure, quiet "Need more licences?" link |
| Running low | Amber banner, **Add More Students** button |
| Exhausted | Red **Student Limit Reached** banner and button |

"Running low" is within a tenth of the allocation, or five licences, whichever
is larger, so a 30-student school is warned at 5 rather than at 3.

The banner appears only when capacity is low or gone, so it reads as a real
prompt rather than furniture the eye learns to skip.

Attempting to exceed the limit gives:

> **Student Limit Reached.** You have reached the maximum number of students
> included in your current Basic Plan allocation. You currently have 100 out of
> 100 student licences in use. To add more students, please make an additional
> payment and submit your payment receipt for approval.

---

## 6. The audit trail

Every allocation is traceable from one `subscription_top_ups` row:

| Column | Meaning |
| ------ | ------- |
| `subscription_id` | The school, via its subscription |
| `reference` | Payment reference |
| `additional_amount` | Amount paid |
| `price_per_student` | Price at the time of submission |
| `additional_students_count` | Students **requested** by the school |
| `approved_students_count` | Students **allocated** by the Super Admin |
| `previous_students_count` | Allocation before approval |
| `new_students_count` | Allocation after approval |
| `receipt_path`, `receipt_original_name` | The receipt |
| `created_at` | Submitted |
| `verified_at`, `verified_by` | Approved, and by whom |
| `status` | pending_verification / approved / rejected |
| `notes` | Rejection reason, or the reviewer's note |

Submission, approval and rejection each also write to `audit_logs`, with the
approval entry recording the requested figure, the allocated figure, and the
before/after allocation.

---

## 7. Worked example

```
Initial      School pays NGN 50,000, requests 100, uploads receipt
             Super Admin verifies, enters 100
             -> allocated 100, used 0, remaining 100

At the cap   100 / 100 used, remaining 0
             Adding a student is refused; the red banner appears

Top-up       School pays NGN 25,000, requests 50, uploads receipt
             Capacity is STILL 100 - the request is pending
             Super Admin verifies, enters 50
             -> allocated 150, used 100, remaining 50
```

---

## Where it lives

| File | Role |
| ---- | ---- |
| `app/Services/StudentLicenceAllocation.php` | The single authority on capacity |
| `app/Http/Controllers/SchoolAdmin/SubscriptionTopUpController.php` | School submits |
| `app/Http/Controllers/SuperAdmin/SubscriptionApprovalController.php` | Super Admin approves |
| `app/Http/Requests/Subscriptions/ApproveTopUpRequest.php` | Requires the allocation figure |
| `app/Http/Controllers/SuperAdmin/PaymentReceiptController.php` | Streams receipts privately |
| `app/Http/Controllers/SuperAdmin/PlanPricingController.php` | Editable pricing |
| `app/Http/Controllers/SchoolAdmin/StudentController.php` | Create and reactivate |
| `tests/Feature/Subscriptions/StudentLicenceAllocationTest.php` | 22 tests |

---

## If you change this

- **Any new way to create or reactivate a student must go through
  `withCapacity()`.** A bulk import or an API endpoint added without it would
  bypass the limit entirely, and no test would notice.
- **Never apply `additional_students_count` to a subscription.** That is the
  school's request. `approved_students_count` is the decision.
- Run `tests/Feature/Subscriptions/StudentLicenceAllocationTest.php` after
  touching student creation, top-ups or plan pricing.
