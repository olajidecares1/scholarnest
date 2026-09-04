---
title: ScholarNest School Operational Runbook
audience: School Admin, Principal, Teachers, Class Teachers, Staff, Students, Parents/Guardians
version: 1.0
last_updated: 2026-09-01
---

# ScholarNest School Operational Runbook

**Version 1.0 · 1 September 2026**

Find your problem. Do the steps. If it still does not work, the entry tells you
exactly what to send the ScholarNest Team.

Every step in this book was checked against how ScholarNest actually behaves. Where
something is not possible on your plan, it says so rather than sending you
looking for a button that is not there.

---

## How to use this book

1. **Find the category** — LOGIN, STUDENTS, RESULTS, ATTENDANCE, CBT, WEBSITE,
   PAYMENTS, SIGNATURES, MESSAGES, MOBILE, SECURITY.
2. **Read the whole entry before doing anything.** Some steps must happen in
   order.
3. **Do not skip to escalation.** Nine problems in ten are on this page.
4. **Security problems are different.** Go straight to
   [Security incidents](#20-security-incidents) and stop.

### Severity

| | Meaning | Response |
| --- | --- | --- |
| 🔴 **Critical** | Security breach, unauthorised access, data loss, nobody can use ScholarNest | **Stop and contact ScholarNest immediately.** Do not troubleshoot. |
| 🟠 **High** | A whole feature is down for the whole school | Work through the entry. Escalate within the day if unresolved. |
| 🟡 **Medium** | One person or one feature affected, a workaround exists | Work through the entry. Escalate if it repeats. |
| 🟢 **Low** | Wrong text, a picture that has not appeared, a cosmetic problem | Fix it yourself. Escalate only if it will not save. |

### Things nobody at a school should ever be asked to do

If any instruction anywhere tells you to do one of these, it is wrong and you
should refuse:

- Change anything in the database
- Run commands on a server
- Edit configuration or `.env` files
- Turn off a security setting
- Share a password with anyone, including the ScholarNest Team

**The ScholarNest Team will never ask for your password.**

---

## The three facts behind most problems

Most confusion in ScholarNest comes down to one of these. Read them once and half
this book becomes obvious.

### 1. Your plan decides what exists

| Available on | Features |
| --- | --- |
| **Every plan** (Basic, Standard, Exclusive) | Students, staff, classes, attendance, examinations, results, result tokens, ID cards, facilities, school settings, **and the Staff portal** |
| **Standard and Exclusive** | Public school website, News, Events, Careers, Testimonials, Co-curricular, **Student portal**, **Parent/Guardian portal**, CBT, CBT Practice, Assignments, Library, Transport, Hostel, Finance, Teacher Diary, Timetable |
| **Exclusive only** | Your own custom domain |

If a feature is not on your plan, the page refuses with a message naming the
feature and the plan that includes it. **This is enforced by the server**, so
typing the address directly will not get you in. That is not a fault.

> **The Exclusive plan cannot currently be bought.** It is built and it works,
> but ScholarNest is not accepting new Exclusive subscriptions yet. Custom domains
> are therefore not available at the moment. The plan selection screen is
> always right about what can be purchased today.

**Basic-plan parents are not shut out of results.** They have no portal account,
but they reach results through a result token, which needs no account at all.

### 2. School portals log you out after 3 minutes of inactivity

Not 30 minutes. **Three.** It applies to School Admins, staff, students and
parents alike, because these accounts hold pupil records and are often used on
shared computers.

**The public school website is NOT affected.** A visitor filling in your contact
form is not signed in and is not logged out of anything, however long they take.

### 3. One school can never see another school's data

This is enforced on the server on every single request. If a school ever sees
data belonging to another school, that is a 🔴 **Critical security incident** —
go to [section 20](#20-security-incidents) and stop.

---

# 1. LOGIN, SESSIONS AND ACCESS

## 1.1 — Cannot log in: "These credentials do not match our records"

**Affected:** anyone · **Plans:** all · **Severity:** 🟡 Medium

**What you see:** the sign-in page reloads with that message under the form.

**In order of likelihood:**

**Step 1 — Are you on the right portal?**
ScholarNest has four separate sign-in pages, and **an account only works on its
own**. A teacher's details will never work on the student portal, however
correct they are.

| You are a | You sign in at |
| --- | --- |
| Teacher or other staff | Staff portal |
| Student | Student portal (Standard/Exclusive only) |
| Parent or guardian | Parent portal (Standard/Exclusive only) |
| School Admin or Principal | School Admin portal |

**Step 2 — Check what you are typing.**
- Caps Lock off.
- No space at the start or end — copy-and-paste from a message often adds one.
- Staff sign in with their **staff number or email**; students with their
  **admission number**; guardians with their **guardian number or email**.
  Check with your School Admin which one your school issues.

**Step 3 — Ask your School Admin to check the account is active.**
A deactivated account gives a *different* message — "This account has been
deactivated. Please contact your school." If you see that one, go to 1.3.

**Step 4 — Ask your School Admin to reset the password.**
They can set a new one from the staff, student or guardian record. You will be
asked to choose your own the first time you sign in (see 1.5).

**Expected result:** you reach your dashboard.

**If it continues:** more than one person at the school cannot sign in →
escalate.

**Send ScholarNest:** school name, the affected role, how many people are affected,
the **exact wording** of the message, and the date and time. **Never send the
password.**

---

## 1.2 — "Too many login attempts. Please try again in N seconds."

**Affected:** anyone · **Plans:** all · **Severity:** 🟡 Medium

**What happened:** ScholarNest locks an account after **5 failed attempts** and
holds the lock for **15 minutes**. This is deliberate — it is what stops someone
guessing their way into your pupils' records.

**Steps:**

1. **Wait.** The message tells you how long. Nobody can shorten it — not your
   School Admin, not the ScholarNest Team.
2. Do **not** keep trying. Each attempt can extend the wait.
3. While waiting, work out the correct details (see 1.1).
4. After the wait, try **once**, carefully.
5. If you are not sure of the password, have the School Admin reset it first
   rather than guessing again.

**Do not:** create a second account for the same person to get around a lockout.
You will end up with two accounts, results split between them, and a much harder
problem.

**Escalate if:** you are locked out without ever having typed a wrong password.
That may mean somebody else is trying to get into your account → treat it as
🔴 [Security](#20-security-incidents).

---

## 1.3 — "This account has been deactivated. Please contact your school."

**Affected:** staff, students, guardians · **Plans:** all · **Severity:** 🟡 Medium

The account exists and the password was right. The school has switched it off.

**School Admin — steps:**

1. Open the staff, student or guardian record.
2. Check whether it is marked inactive.
3. **Before reactivating a student, check your capacity.** Reactivating uses a
   pupil place, and ScholarNest will refuse if you are full. See 3.5.
4. Reactivate, then have the person try again.

**If it was deactivated on purpose** (a pupil who left, a teacher who resigned),
that is correct behaviour. Do not reactivate to make the message go away.

---

## 1.4 — Logged out after a couple of minutes

**Affected:** all portal users · **Plans:** all · **Severity:** 🟢 Low — this is by design

**This is not a fault.** School portals sign you out after **3 minutes without
activity**, because these accounts hold children's records and are often used on
shared staffroom computers.

**What counts as activity:** loading a page, saving, moving between screens.
**What does not:** typing into a box without saving. A long remark typed slowly
can time out mid-sentence.

**How to work with it:**

1. **Save often.** Especially when entering scores or writing remarks.
2. For long remarks, type them elsewhere first and paste them in.
3. Entering a full class of scores? Save each subject as you finish it rather
   than at the end.
4. Sign out properly when leaving a shared computer.

**If you are signed out while actively working** — clicking and saving, not
idle — that is a fault. Escalate with what you were doing and how long you had
been doing it.

---

## 1.5 — "You must change your password before continuing"

**Affected:** staff, students, guardians · **Plans:** all · **Severity:** 🟢 Low

Your School Admin set a temporary password. ScholarNest will not let you use the
portal until you replace it — the person who typed it in should not go on
knowing it.

**Steps:**

1. You will be taken to the settings page. Everything else is blocked until you
   finish.
2. Choose a new password: **at least 8 characters, with a capital, a lowercase
   letter, a number and a symbol.**
3. Save.
4. You now have full access.

**Do not** give this new password to anyone, including your School Admin. They
can reset it again if you forget; they never need to know it.

---

## 1.6 — "Your session expired while this page was open. Please try again."

**Affected:** anyone · **Plans:** all · **Severity:** 🟢 Low

The page had been open too long before you submitted it. ScholarNest deliberately
sends you back **with what you typed still in the boxes** rather than losing it.

**Steps:**

1. Check your details are still filled in — they should be.
2. Type your password again if it was a sign-in form (passwords are never kept).
3. Submit again.

**If it happens every time**, even on a fresh page: your browser is blocking
cookies. Go to [section 19](#19-browser-troubleshooting), Level 2.

---

## 1.7 — The portal link does not work / gives "page not found"

**Affected:** anyone · **Plans:** all · **Severity:** 🟠 High if nobody can get in

**Why:** every portal sign-in page sits behind a long, unguessable address
unique to your school. Knowing your school's name is not enough to find it —
that is on purpose. A wrong or altered address gives **page not found**, never
"access denied", because saying "access denied" would confirm something is
there.

**Steps:**

1. **Use the exact link your School Admin gave you.** Do not shorten it, retype
   it, or guess it.
2. Copy and paste rather than typing — these addresses are long and one wrong
   character breaks them.
3. Check nothing was cut off. Messaging apps often truncate long links; ask for
   it again as plain text.
4. If you bookmarked it, the bookmark may be stale — ask for a fresh link.

**School Admin:** the current links are in your dashboard. Share them by a
method that will not break them.

**Basic-plan schools** come in a different way: one shared entry address for all
Basic schools, then you type your school's name. Your School Admin has it.

**Escalate if:** the School Admin's own copy of the link does not work either.

---

## 1.8 — Signed out of the portal, and now the school website looks wrong

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟡 Medium

**These are two different things and must not affect each other.** The public
website is for visitors and has no sign-in. The portal is for staff.

**Steps:**

1. Open the public website **in a private/incognito window**. That is what a
   visitor actually sees.
2. If it looks right there → nothing is wrong with your website. You were
   looking at a cached page in your normal window. Reload with **Ctrl+F5**
   (**Cmd+Shift+R** on a Mac).
3. If it looks wrong in private too → this is a website problem, not a session
   problem. Go to [section 11](#11-public-school-website).

**Escalate if:** a visitor with no account is sent to a sign-in or registration
page when opening your website. That is a genuine fault — send the exact address
they used.

---

## 1.9 — Signed in on one tab, signed out on another

**Affected:** anyone · **Plans:** all · **Severity:** 🟢 Low

**Steps:**

1. Reload the tab that shows you as signed out. Tabs do not update each other
   until they reload.
2. If it asks you to sign in, sign in — the 3-minute timeout applies to the
   whole browser, not per tab.
3. **Do not sign into two different portals in the same browser at once.** Use
   separate browsers, or a private window for the second.

---

## 1.10 — "Remember me" — should we use it?

**Affected:** anyone · **Plans:** all · **Severity:** 🟢 Low

It keeps you signed in after closing the browser.

- ✅ Your own phone or your own locked computer.
- ❌ **Never** on a shared staffroom computer, a library machine, or anything a
  pupil can reach. It leaves the browser signed in to pupil records after you
  have walked away.

It is off unless you tick it.

---

# 2. STAFF AND TEACHERS

## 2.1 — Creating a teacher account

**Affected:** School Admin · **Plans:** all · **Severity:** 🟢 Low

1. Staff → add a new member.
2. Fill in name, role (Teacher), and contact details.
3. ScholarNest issues a staff number, or you enter one if your school sets its own.
4. Set a temporary password. **They will be forced to change it** on first
   sign-in (see 1.5).
5. Give them the staff portal link (1.7) and their staff number.

**A new teacher account can do almost nothing until it is assigned to classes
and subjects.** That is step 2.2, and skipping it causes most of section 2.

---

## 2.2 — Teacher signs in but sees no classes or subjects

**Affected:** teacher · **Plans:** all · **Severity:** 🟠 High for that teacher

**Cause, almost always:** no assignment. A teacher sees exactly the classes and
subjects they have been assigned to, and nothing else.

**ScholarNest has two kinds of assignment and they do different things:**

| Assignment | What it grants |
| --- | --- |
| **Class Teacher** of a class | The whole class — attendance, all its results, the class teacher remark |
| **Subject Teacher** for a class *and* a subject | Score entry for **that subject in that class only** |

**School Admin — steps:**

1. Open Teacher Assignments.
2. Find the teacher. If nothing is listed, that is your answer.
3. Add the assignments they actually need:
   - Form teacher of JSS 2A → **Class Teacher, JSS 2A**.
   - Teaches Maths to JSS 1A, 1B and 1C → **three Subject Teacher assignments**,
     one per class. One assignment does not cover three classes.
4. Check the class name matches your class list **exactly**. "JSS 1A" and
   "JSS1A" are two different classes to ScholarNest — one of them will be empty.
5. Have the teacher sign out and back in.

**Expected result:** the teacher sees their classes on their dashboard.

**Do not:** create a second account for the teacher because the first shows
nothing. The account is fine; the assignment is missing.

---

## 2.3 — Teacher can see the class but cannot enter scores for a subject

**Affected:** subject teacher · **Plans:** all · **Severity:** 🟡 Medium

They are Class Teacher (so they see the class) but have no Subject Teacher
assignment for that subject.

**Steps:**

1. School Admin → Teacher Assignments.
2. Add **Subject Teacher** for that teacher, that class, that subject.
3. Check the subject name matches the examination's subject exactly.
4. Teacher signs out and back in.

**Note:** being Class Teacher of a class does not by itself grant score entry
for every subject in it. Assign each subject deliberately — that is what stops a
teacher editing marks for a subject they do not teach.

---

## 2.4 — Teacher has left / changing who teaches a class

**Affected:** School Admin · **Plans:** all · **Severity:** 🟡 Medium

**Do this, in order:**

1. **Remove the old assignments first.** Otherwise both teachers can enter
   scores for the same class.
2. Create or find the new teacher's account.
3. Add the same assignments to the new teacher.
4. **Deactivate the departing teacher's account** the day they leave. An active
   account still reaches pupil records.
5. Check whether their signature appears on results — see 10.6.

**Do not delete** a teacher who has already entered results or signed report
cards unless you are certain. Deactivating stops access and keeps the record.
**Deletion in ScholarNest is permanent and cannot be undone.**

---

## 2.5 — Staff details are wrong

**Affected:** School Admin · **Plans:** all · **Severity:** 🟢 Low

1. Staff → open the record → correct it → save.
2. If the staff member submitted a change request, approve it from the same
   screen.

**Staff cannot edit their own name, staff number or role.** That is deliberate.
They request a change and you approve it.

---

# 3. STUDENTS

## 3.1 — Adding a student

**Affected:** School Admin · **Plans:** all · **Severity:** 🟢 Low

1. Students → add.
2. Name, class and gender are required. Everything else can follow.
3. **Pick the class from your class list**, do not type it. A typed class name
   creates a class nobody is in.
4. Save.

**If you are refused, see 3.5 — you are probably at capacity.**

---

## 3.2 — Student cannot sign in to the student portal

**Affected:** student · **Plans:** Standard, Exclusive · **Severity:** 🟡 Medium

> **On the Basic plan there is no student portal.** This is not a fault. Basic
> pupils reach their results by result token instead — see 4.7.

**Steps:**

1. Confirm your plan includes the student portal (Standard or Exclusive).
2. Confirm the pupil is using the **student** portal link, not staff or parent.
3. Confirm they are typing their **admission number** exactly as printed —
   leading zeros matter.
4. School Admin: check the pupil's record is **active** (1.3).
5. School Admin: check a password has been set. A pupil record can exist with no
   password, and then there is nothing to sign in with.
6. Reset the password; the pupil sets their own on first sign-in.

---

## 3.3 — Student is in the wrong class

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High — it affects results and attendance

**Fix this before entering any more results.** Class decides which examinations,
attendance register and teachers apply to a pupil.

1. Students → open the record → change the class → save.
2. **Check for results already entered under the old class.** They stay attached
   to the old class's examination. If a result was already entered, tell the
   class teacher of both classes.
3. **Check any result tokens already issued.** A token is tied to one pupil and
   one examination; moving the pupil does not move the token. Issue a new one if
   the old examination no longer applies.
4. Check attendance already taken under the old class.

**Escalate if:** results have already been published to parents under the wrong
class. Say which pupil, which examination, and what the correct class is.

---

## 3.4 — Student photograph not showing

**Affected:** School Admin · **Plans:** all · **Severity:** 🟢 Low

1. Check a photograph was actually uploaded — the record shows a placeholder if
   not.
2. Check the file type: **JPG, PNG or WebP only**. HEIC from an iPhone is not
   accepted; convert it first.
3. Re-upload and save.
4. Reload with **Ctrl+F5** — the browser often holds the old image.
5. Check in a private window to rule out caching entirely.

**If the upload is refused**, see [section 17](#17-file-uploads).

---

## 3.5 — "Student capacity reached" — cannot add a pupil

**Affected:** School Admin · **Plans:** Basic, Standard · **Severity:** 🟠 High

Basic and Standard are priced per pupil. Your subscription includes a set number
of places, and **ScholarNest will refuse to go past it.** This is not a bug.

**Step 1 — Free up places you are not using.**
Capacity counts **active** pupils only. Deactivating a pupil who has left
releases their place immediately, and keeps their records.

1. Students → filter for pupils who have left, graduated or transferred.
2. Deactivate them. Do **not** delete them — deletion is permanent and takes
   their results with it.
3. Try adding again.

**Step 2 — If you genuinely need more places, buy them.**

1. Subscription → add student capacity.
2. Choose how many.
3. Pay by bank transfer and upload the receipt.
4. Wait for the ScholarNest Team to approve it. **This is a manual review, not
   instant.**
5. Approved top-ups **add** to your existing allocation — they never replace it.

**While you wait:** you cannot exceed capacity. Plan intake around this.

**Do not:** delete pupils to make room. You lose their academic history
permanently, and it cannot be recovered.

---

## 3.6 — Deleted a student by mistake

**Affected:** School Admin · **Plans:** all · **Severity:** 🔴 Critical for that pupil

**Deletion in ScholarNest is immediate and permanent.** There is no recycle bin, no
undo, and the ScholarNest Team cannot restore one pupil.

**Steps:**

1. **Stop.** Do not delete anything else.
2. Write down: the pupil's name, admission number, class, and roughly when it
   happened.
3. **Contact the ScholarNest Team immediately.** The sooner you ask, the more chance
   there is — recovery depends on backups, is not guaranteed, and would restore
   far more than the one pupil.
4. Meanwhile, gather any printed report cards or exported records you hold.

**Prevention:** deactivate instead of deleting. It frees the pupil place, stops
sign-in, and keeps everything.

---

# 4. RESULTS AND REPORT CARDS

This is the longest section because it is where most questions arise.

## How a result gets to a parent

Every failure below is a step in this chain that has not happened yet.

```
Examination created (School Admin)
        ↓
Subjects added to the examination
        ↓
Teacher enters TEST score and EXAM score for each subject
        ↓
Total, grade and position calculated automatically
        ↓
Class teacher remark    Principal remark   (recommended, not required)
        ↓
Result PUBLISHED to the repository
        ↓
Fee check — is anything outstanding?
        ↓
Parent reads it: portal (Standard/Exclusive) or result token (any plan)
```

---

## 4.1 — Teacher cannot enter results

**Affected:** teacher · **Plans:** all · **Severity:** 🟡 Medium

**Decision path:**

```
Can the teacher see the class at all?
   NO  → No assignment. Go to 2.2.
   YES ↓
Can they see the subject?
   NO  → No Subject Teacher assignment for it. Go to 2.3.
   YES ↓
Does the examination exist and include that subject?
   NO  → School Admin creates it / adds the subject. Go to 4.2.
   YES ↓
Are the boxes there but refusing to save?
   YES → Go to 4.3.
   NO  → Escalate.
```

---

## 4.2 — Examination or subject is missing

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

Teachers cannot create examinations. If it does not exist, nobody can enter
anything.

1. Examinations → create.
2. Set the session, the term and the class.
3. **Add every subject that class is examined in.** A subject not on the
   examination cannot be scored, and its absence will block publishing later.
4. Tell the teachers it is ready.

**Check the class name matches your class list exactly.** An examination created
for "JSS1A" when your pupils are in "JSS 1A" will look empty and nobody will
understand why.

---

## 4.3 — Score will not save

**Affected:** teacher · **Plans:** all · **Severity:** 🟡 Medium

**Step 1 — Check the number.**
- Whole numbers within the maximum for that component.
- No letters, no percent sign, no spaces.
- A blank box is not zero. If a pupil scored nothing, type **0**.

**Step 2 — Enter both marks.**
ScholarNest expects a **test score and an examination score** for each subject. One
without the other will block publishing later even if it saves now.

**Step 3 — Watch the 3-minute timeout.**
Entering a full class slowly can time you out. **Save each subject as you
finish it**, not at the end. If you are signed out mid-entry, sign back in and
check what saved.

**Step 4 — Retry.**
Save one pupil. If that works, the rest will.

**Escalate if:** one pupil's score will not save while the rest of the class
saves fine. Send the pupil's admission number, the subject, and the number you
are trying to enter.

---

## 4.4 — Total, grade or position is wrong

**Affected:** School Admin, class teacher · **Plans:** all · **Severity:** 🟡 Medium

**ScholarNest calculates these from what you entered and how you configured
grading.** It does not invent them. A wrong output means a wrong input.

**Step 1 — Check the marks.** Open the pupil's result and check each test and
exam score against your mark sheet. Most "wrong grades" are a mistyped mark.

**Step 2 — Check your grade bands.**
Academics → grade bands. Look for:
- **Gaps** — if A is 70–100 and B is 60–69, what happens at 69.5?
- **Overlaps** — two bands covering the same mark; one silently wins.
- Bands not covering 0 to 100.

**Step 3 — Position.** Position is worked out within the class for that
examination. A pupil in the wrong class (3.3) or with a missing subject will
place oddly.

**Step 4 — Fix the input and reopen the result.** It recalculates; there is no
separate "recalculate" button.

**Escalate if:** the marks are right, the bands are right, and the grade is
still wrong. Send the pupil's admission number, the examination, the marks, and
a screenshot of your grade bands.

---

## 4.5 — Cannot publish a result: "no score recorded for…"

**Affected:** School Admin, class teacher · **Plans:** all · **Severity:** 🟡 Medium

**This is ScholarNest protecting you.** A parent who opens a report card with three
subjects and comes back to find nine was not given a corrected result — they
were given an unfinished one and told it was final.

**The message names exactly what is missing.** Read it.

| Message | What to do |
| --- | --- |
| "This examination has no subjects yet" | School Admin adds subjects to the examination (4.2) |
| "No score has been recorded for *Mathematics and Civic Education*" | The teachers for those subjects enter marks |
| "Both the test and examination marks are needed for *English*" | One of the two is missing — enter it |

**Remarks do not block publishing.** You will be told a class teacher or
principal remark is missing, and you can publish anyway. That is a warning, not
a refusal.

**Steps:**

1. Read which subjects are named.
2. Contact those subject teachers.
3. Once entered, publish again.
4. Publishing a whole class at once **skips** any pupil who is not ready and
   tells you which — it does not fail the whole class over one pupil.

---

## 4.6 — Result is published but the parent cannot see it

**Affected:** parent, School Admin · **Plans:** all · **Severity:** 🟠 High

**Step 1 — Check for unpaid fees. This is the most common cause and it surprises
everyone.**

> **ScholarNest withholds a result while the pupil owes the school money.**
> The gate only closes on a balance **your school itself raised** — a school with
> no fees recorded on ScholarNest owes nothing and nothing is withheld.

**School Admin:**
1. Open the pupil's fees and check the outstanding balance.
2. If it is genuinely owed → the parent pays, you record the payment, and the
   result appears.
3. If it is wrong, or you are granting a bursary or payment plan → **release
   that pupil's results for the term.** This records who made the decision. It
   is your school's call, always.

**Step 2 — Check it was actually published.** Entered is not published. Open the
result and confirm it has been sent to the repository.

**Step 3 — Check the parent is looking in the right place.**

| Plan | Where a parent reads results |
| --- | --- |
| Basic | Result token only — no parent account exists |
| Standard, Exclusive | Parent portal, **or** a result token |

**Step 4 — Check the parent is linked to the child.** See 5.3.

---

## 4.7 — Result token will not work

**Affected:** parent · **Plans:** all · **Severity:** 🟡 Medium

**How tokens work — this explains almost every failure:**

- A token is tied to **one pupil and one examination**, fixed when it is
  created. It is not a general password.
- It works a **limited number of times** (5 by default). A token passed around a
  class WhatsApp group stops working.
- Every use is logged with the time and the device. Your school can see this.

**Decision path:**

```
Is the token typed exactly as issued?
   NO  → Retype carefully. Watch 0/O and 1/l. No spaces.
   YES ↓
Is it the token for THIS pupil and THIS examination?
   NO  → School Admin issues the right one. A sibling's token will not work.
   YES ↓
Has it been used up?
   YES → School Admin issues a fresh one.
   NO  ↓
Has the result been published?
   NO  → Go to 4.5.
   YES ↓
Does the pupil owe fees?
   YES → Go to 4.6, Step 1.
   NO  → Escalate.
```

**"Too many attempts":** result lookups are rate-limited per school. Wait, then
try once with the correct token.

**School Admin — issuing a token:**
1. Result tokens → issue.
2. Choose the pupil **and** the examination. Both are required and both are
   locked in.
3. Give it to that family only.

**If a token has been shared publicly:** treat it as 🔴 a security matter — see
20.4.

---

## 4.8 — Wrong pupil's result, or a result on the wrong pupil

**Affected:** School Admin · **Plans:** all · **Severity:** 🔴 Critical

A pupil seeing another pupil's result is a data protection incident, not a
cosmetic bug.

**Steps:**

1. **Stop issuing and publishing results for that examination.**
2. Screenshot exactly what was seen. **Do not delete anything** — it is the
   evidence.
3. Note: which pupil saw it, whose result it was, how they reached it (portal,
   token, printed card), the date and time.
4. **Contact the ScholarNest Team immediately.** Mark it 🔴 Critical.
5. If it reached a parent, follow your school's own data-protection procedure —
   you are the one who must notify them, not ScholarNest.
6. Do not attempt to investigate the cause yourself.

**Common innocent explanation, still worth checking first:** two pupils with the
same or similar name, and the wrong one was picked from a list. Check the
**admission numbers**, which are unique. If it was a mis-click, correct it and
note it — no escalation needed.

---

## 4.9 — Result on a report card is out of date after a correction

**Affected:** School Admin · **Plans:** all · **Severity:** 🟡 Medium

A published result is a snapshot. Correcting a score does not rewrite what was
already sent.

**Steps:**

1. Correct the score.
2. **Publish the result again.** This is the step people miss.
3. If the family already has a token, the corrected result appears the next time
   they open it — unless the token is used up, in which case issue a new one.
4. Tell the parent. A quietly changed grade is worse than a corrected one
   explained.

---

## 4.10 — Report card will not print, or prints wrongly

**Affected:** School Admin, class teacher · **Plans:** all · **Severity:** 🟡 Medium

1. Use the **print or PDF** option in ScholarNest, not the browser's own print.
2. If it is cut off, set the printer to **A4** and margins to default.
3. If the logo or signature is missing, see 10.5 — that is a separate problem.
4. If the page is blank, check the result is complete (4.5).
5. Try a different browser (section 19, Level 4).

**Escalate if:** the PDF downloads but will not open, or is empty. Send the
pupil's admission number and the examination.

---

# 5. PARENTS AND GUARDIANS

> **Parent and guardian accounts are Standard and Exclusive only.** On Basic
> there are no parent accounts at all — parents use result tokens (4.7). This is
> a plan limit, not a fault.

## 5.1 — Creating a parent account

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

1. Guardians → add.
2. Name, phone and email.
3. **Link them to their children.** An account with no children linked shows an
   empty dashboard — this is the single most common parent complaint.
4. Set a temporary password; they must change it on first sign-in (1.5).
5. Send them the **parent portal** link (1.7).

---

## 5.2 — Parent cannot sign in

**Affected:** parent · **Plans:** Standard, Exclusive · **Severity:** 🟡 Medium

Work through 1.1. Then, specific to parents:

1. Confirm they are on the **parent** portal, not the student one. Parents often
   try their child's link.
2. Confirm they are using their **guardian number or email**, not their child's
   admission number.
3. School Admin: check the guardian record is active.

---

## 5.3 — Parent signs in but sees no children (or the wrong child)

**Affected:** parent · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

**No children shown → the link was never made.**

1. School Admin → Guardians → open the record.
2. Link each child.
3. Parent signs out and back in.

**The wrong child shown → 🔴 STOP.**

A parent seeing a child who is not theirs is a data protection incident.

1. Screenshot it. Do not delete anything.
2. Note which parent, which child, and when.
3. **Contact the ScholarNest Team immediately.**
4. Check first whether somebody simply linked the wrong child by hand — two
   pupils with similar names. If so, unlink it, note what happened, and tell the
   parent. Still record it; it is still a disclosure.

---

# 6. ATTENDANCE

## 6.1 — Cannot take attendance

**Affected:** teacher · **Plans:** all · **Severity:** 🟡 Medium

1. **Check the date.** ScholarNest will not accept attendance for a **future
   date**. Today or earlier only.
2. Check the teacher is Class Teacher of that class (2.2).
3. Check the class has active pupils in it.

---

## 6.2 — A pupil is missing from the register

**Affected:** teacher · **Plans:** all · **Severity:** 🟡 Medium

The register lists **active pupils in that class**. A pupil is missing because
one of those is not true.

1. School Admin: is the pupil **active**? (1.3)
2. Is the pupil in **this** class? (3.3)
3. Is the class name spelled the same on the pupil and the register?
4. Fix it, then reload the register.

---

## 6.3 — Attendance was marked wrongly

**Affected:** teacher, School Admin · **Plans:** all · **Severity:** 🟢 Low

1. Open attendance for **that date** — not today.
2. Change the marks.
3. Save.

Correcting an old date is allowed. Correcting a future date is not, because you
cannot take it in the first place.

---

# 7. EXAMINATIONS

Covered under [Results](#4-results-and-report-cards): creating examinations
(4.2), score entry problems (4.1, 4.3), calculation (4.4). Nothing in
examinations fails on its own — it fails as one of those.

**One point worth its own note:**

## 7.1 — An examination shows no pupils

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

Almost always a class name that does not match.

1. Open the examination and note the class name **exactly**.
2. Open your class list and compare character by character. "JSS 1A" ≠ "JSS1A"
   ≠ "Jss 1a".
3. Correct whichever is wrong — usually easier to correct the examination.
4. Reload.

---

# 8. CBT (COMPUTER-BASED TESTS)

> **CBT is Standard and Exclusive only.** On Basic the pages are refused by the
> server with a message naming the plan required. This is not a fault.

## 8.1 — Uploaded a document and it is stuck on "Waiting to start…"

**Affected:** teacher, School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

**Your document is safe.** ScholarNest reads uploaded documents in the background,
and the background service has stopped. The upload succeeded; the reading has
not started.

**How to tell where you are:**

| Status | Meaning |
| --- | --- |
| Waiting to start… | Queued. Nothing has begun. |
| Reading the document and extracting questions… | Working. No percentage — the reader cannot say how far through it is. |
| Creating the CBT from the extracted questions… | Nearly done. |
| Extracted — needs an exam body and subject | **Your turn.** See 8.3. |
| Questions imported successfully | Done. |
| Extraction failed | See 8.2. |

**Steps:**

1. Wait 5 minutes and reload. Extraction of a large PDF is genuinely slow.
2. **Do not upload it again.** A second copy makes two jobs, not a faster one.
3. If it is still "Waiting to start…" after 15 minutes, **contact the ScholarNest
   Team.** Only they can restart the service.
4. When it comes back your document is read automatically. You do not re-upload.

**Send ScholarNest:** school name, who uploaded, the file name, when, and the status
shown. Say clearly: *"CBT extraction stuck at Waiting to start."*

---

## 8.2 — "Extraction failed"

**Affected:** teacher · **Plans:** Standard, Exclusive · **Severity:** 🟡 Medium

The document was read and could not be understood.

**Steps:**

1. **Check the file type.** Word (.docx) and PDF are what the reader handles. A
   scanned PDF — a photograph of a page — has no text in it to read, however
   clear it looks.
2. **Check the layout.** Questions numbered plainly with options labelled A, B,
   C, D read reliably. Multi-column layouts, text inside images, and heavy
   tables often do not.
3. **Try a cleaner copy.** Save from Word as .docx rather than exporting an
   image-heavy PDF.
4. **Try a smaller batch.** Twenty questions to test the format before uploading
   two hundred.
5. If a clean, plainly formatted document still fails, escalate and **attach the
   document** — the ScholarNest Team cannot diagnose it without the file.

---

## 8.3 — "Needs exam body and subject"

**Affected:** teacher, School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

Extraction worked. ScholarNest could not tell what the questions belong to.

1. Open the upload.
2. Choose the exam body (WAEC, JAMB, NECO…) and the subject.
3. Save. The CBT is created from there.

---

## 8.4 — Questions came out wrong

**Affected:** teacher · **Plans:** Standard, Exclusive · **Severity:** 🟡 Medium

**Always check before publishing to pupils.** Automatic reading is not perfect.

1. Preview the CBT.
2. Correct any question, option or answer by hand.
3. Watch particularly for: mathematical symbols, superscripts, diagrams (which
   cannot be read at all), and options that ran together.
4. Only publish once you have read it through.

**If most questions are wrong**, the document format is the problem — go back to
8.2 and re-upload a cleaner copy rather than fixing a hundred questions by hand.

---

## 8.5 — Pupils cannot see a test

**Affected:** student · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

1. Is the test **published**, or still a draft?
2. Is it assigned to **their class**?
3. Are the pupils on the student portal, and does your plan include it?
4. Has the test been **locked**? A locked test cannot be started.

---

## 8.6 — Cannot lock or unlock a test

**Affected:** teacher · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

You will see: *"This test cannot be locked or unlocked — students have already
started it. You can still archive it."*

This is deliberate. Changing a test out from under pupils mid-attempt would
invalidate their work.

- To stop new pupils starting → **archive** it.
- Pupils already in progress finish normally.

---

## 8.7 — A pupil's CBT score was not recorded

**Affected:** student, teacher · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

1. Check the attempt exists — teachers can see attempts per test.
2. Check whether the pupil actually **submitted** or just closed the tab.
3. Check whether they lost connection mid-test.
4. If an attempt exists with answers but no score, escalate with the pupil's
   admission number, the test name and the date.

**Do not** let the pupil retake it before checking. A second attempt can make
the first harder to recover.

---

# 9. ASSIGNMENTS, LIBRARY, TRANSPORT, HOSTEL, FINANCE, DIARY, TIMETABLE

> **All of these are Standard and Exclusive only.** On Basic they are refused by
> the server with a message naming the required plan.

They share one troubleshooting path:

1. **"You need to upgrade" message** → the feature is not on your plan. Not a
   fault.
2. **Page loads but is empty** → nothing has been created yet, or a teacher is
   not assigned to the class (2.2).
3. **Cannot save** → check required fields; check the 3-minute timeout (1.4).
4. **A teacher cannot see it** → check their assignment (2.2, 2.3).

**Finance has one effect people do not expect:** an outstanding balance
**withholds that pupil's results**. See 4.6.

---

# 10. DIGITAL SIGNATURES

## 10.1 — What a signature is in ScholarNest

**Affected:** School Admin, class teacher · **Plans:** all · **Severity:** —

You draw it once with a finger, stylus or mouse. It is saved as a picture and
printed on report cards and ID cards.

**Two rules that explain most problems:**

1. **A signature always belongs to the person signed in when it was drawn.** You
   cannot draw one "on behalf of" the principal — it will be recorded as yours.
2. **One person, one signature.** Drawing a new one replaces the old
   everywhere. There is no history.

---

## 10.2 — The signature pad will not open

**Affected:** anyone signing · **Plans:** all · **Severity:** 🟡 Medium

1. Reload the page (**Ctrl+F5**).
2. Try a different browser (section 19).
3. On a phone or tablet, turn it to landscape — the pad needs width.
4. Check nothing is blocking scripts (some school networks do).

---

## 10.3 — Cannot draw / the line does not follow my finger

**Affected:** anyone signing · **Plans:** all · **Severity:** 🟡 Medium

1. **Draw slowly.** Fast strokes on a phone can drop.
2. Use a finger or stylus, not a fingernail.
3. Remove any screen protector that is interfering.
4. On a laptop, use the trackpad with the button held, or a mouse.
5. Clear and start again — a partial stroke can confuse the pad.

---

## 10.4 — "That is not a signature image" / it will not save

**Affected:** anyone signing · **Plans:** all · **Severity:** 🟡 Medium

ScholarNest only accepts what the pad itself produces, and it checks the picture is
sensible before keeping it.

1. **Draw something.** An empty pad has nothing to save.
2. Draw within the box, not off the edge.
3. Do not try to upload a photograph of a signature — the pad is for drawing.
4. Reload and draw again.

**Escalate if:** a normal drawn signature is refused repeatedly on more than one
device.

---

## 10.5 — Signature is not on the report card

**Affected:** School Admin, parent · **Plans:** all · **Severity:** 🟡 Medium

```
Has that person registered a signature at all?
   NO  → They sign in and draw one. Nobody can do it for them.
   YES ↓
Is it the right person?
   The class teacher's signature comes from the CLASS TEACHER of that class.
   The principal's comes from the School Admin account that drew it.
   Wrong class teacher assigned → fix the assignment (2.2), then reprint.
   YES ↓
Reprint the report card.
   A card printed before the signature existed will not have it.
   Still missing → escalate.
```

---

## 10.6 — Wrong signature appearing / a departed teacher's signature

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

A signature on a document is a claim that a specific person signed it.

**Steps:**

1. Check who is assigned as **Class Teacher** of that class. That is where the
   class teacher signature comes from.
2. If the assignment is wrong, correct it (2.2) and reprint. Do not just reprint.
3. If a teacher has left, assign their replacement and have the replacement draw
   their own signature.
4. **Never ask one person to draw another's signature.** It is recorded against
   whoever is signed in, so it would be a false attribution on a school
   document.

**Escalate if:** a signature appears that belongs to someone from another
school → 🔴 [Security](#20-security-incidents).

---

## 10.7 — Replacing a signature

**Affected:** the signer · **Plans:** all · **Severity:** 🟢 Low

1. The person signs in **as themselves**.
2. Opens the signature pad and draws a new one.
3. It replaces the old one everywhere immediately.
4. Reprint any documents that should carry the new one — already-printed cards
   keep the old.

---

# 11. PUBLIC SCHOOL WEBSITE

> **The public website is Standard and Exclusive only.** Basic-plan schools have
> no public website. This is a plan limit, not a fault.

## 11.1 — The website does not load at all

**Affected:** visitors · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

```
Is your school ACTIVE with a current subscription?
   NO  → Nothing public shows. Go to section 13.
   YES ↓
Is the website PUBLISHED?
   NO  → Website settings → Publish. Until then only you can see it.
   YES ↓
Are you using the exact published address?
   NO  → Copy it from your website settings.
   YES ↓
Does it load in a private/incognito window?
   YES → Your normal browser has a stale copy. Ctrl+F5.
   NO  ↓
Does it load on mobile data instead of school Wi-Fi?
   YES → Your school network is blocking it. Talk to whoever runs it.
   NO  → Escalate.
```

---

## 11.2 — A visitor is sent to a sign-in or registration page

**Affected:** visitors · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

**This should never happen.** The public website has no sign-in, and a visitor
should never be sent to one.

**Check first — is it only happening to you?** Open the site in a private
window. If it is fine there, you were seeing your own signed-in session, not
what visitors see. Nothing is wrong.

**If it happens in a private window:** escalate. Send the exact address used,
what appeared, and whether it happens every time.

---

## 11.3 — The website shows old content after an edit

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

1. Did you **save**? Then did you **publish**? Some changes need both.
2. **Ctrl+F5** (**Cmd+Shift+R** on Mac) to force a fresh copy.
3. Check in a private window — that is the visitor's view.
4. Check on your phone, on mobile data. If it is right there, it is your
   computer's cache, not the website.

---

## 11.4 — Hero image problems

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

**Nothing showing:**
1. Check an image was uploaded.
2. **If you have added Hero Slider images, the single fallback image is not
   used.** That is by design — the slider replaces it.
3. JPG, PNG or WebP only.

**Image looks cropped or the wrong part shows:**
The hero fills the whole width of the screen, and screens are different shapes.
Use a **wide landscape photograph** with the important part near the middle. A
tall portrait photograph will always crop badly.

**Image looks soft or blurry:**
It is being stretched across the full screen width. Upload a larger original —
at least 1600 pixels wide.

**Slider not changing:**
1. You need **two or more** slides. One slide does not rotate.
2. Ctrl+F5.
3. Check on another device.

---

## 11.5 — A section is missing from the website

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

**Every section always renders, whether or not you have filled it in.** An empty
Gallery says so rather than disappearing — a parent learning "no photos yet" is
better than a menu link that goes nowhere.

So a "missing" section usually means **empty**:

| Section | Fill it in at |
| --- | --- |
| About, Mission, Vision, Values | Website → About |
| Academic Excellence | Academics → your class levels |
| Latest News | News (Standard+) |
| Upcoming Events | Events (Standard+) |
| Gallery | Website → Gallery |
| Our Facilities | Facilities (**available on every plan**) |
| Contact | Website → Contact details |

**Note:** *Facilities is not in the top menu.* The section is still on the page
and links to it still work — it was taken off the menu to keep it fitting on a
phone.

---

## 11.6 — News or events not appearing

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

1. Is the item **published**, or saved as a draft?
2. **Events show upcoming ones.** An event whose date has passed drops off. That
   is correct.
3. They rotate **three at a time**. Yours may be in the next group — wait, or
   scroll the panel.
4. Ctrl+F5.

---

## 11.7 — Gallery viewer problems

**Affected:** visitors · **Plans:** Standard, Exclusive · **Severity:** 🟢 Low

1. **Image will not open full-screen:** reload; try another browser.
2. **Next/previous not working:** the arrows move through the **whole** gallery,
   not just the three on screen. At the first or last image, one arrow is
   correctly unavailable.
3. **Nothing in the gallery:** no images uploaded yet.
4. **An image is missing:** check it uploaded; JPG, PNG or WebP only.

---

## 11.8 — Contact form messages are not arriving

**Affected:** School Admin · **Plans:** Standard, Exclusive · **Severity:** 🟠 High

**Messages do not go to your email. They arrive inside ScholarNest.**

1. Sign in as School Admin → check your messages.
2. Check notifications.
3. **Test it yourself:** open your website in a private window, send a message,
   then check.
4. If your test arrives, the form works — the earlier sender may not have
   completed it.

**Escalate if:** your own test message does not arrive. Send the time you sent
it and what you typed.

**Conduct reports** from members of the public arrive the same way, with any
photographs attached. These concern children — handle them under your school's
own safeguarding procedure. ScholarNest does not read or investigate them.

---

# 12. WEBSITE CUSTOMISATION

> Standard and Exclusive only.

**Every setting works the same way:** change it → save → **Ctrl+F5** to see it →
check in a private window if unsure.

| Setting | What it controls | If the change does not show |
| --- | --- | --- |
| **Brand colour** | Buttons, headings, icons, borders and links across your whole website | Ctrl+F5. Enter a valid colour — a name like "Indigo" or a code like `#1877f2` |
| **Font family** | The typeface for all your website text | See the note below on installed fonts |
| **Font weight** | How heavy the body text is. Headings keep their own weight so the page keeps its shape | Ctrl+F5 |
| **Hero image / slider** | The large picture at the top | See 11.4 |
| **About, Mission, Vision, Values** | The About section | Save, then Ctrl+F5 |
| **Contact details** | Used in the top bar, Contact section, footer and Contact page — entered once, used everywhere | Save; check you edited Contact Details, not a Contact card |
| **Social links** | The icons in the footer | Paste the **full** web address. Blank = no icon shown |
| **Footer** | Description, contact lines, the call-to-action band | Quick Links and Academics are built automatically and are not edited |
| **Navigation menu** | The links across the top | **Adding even one custom link replaces the whole standard menu.** Add all of them, or none |

> **Fonts marked "Installed fonts — only for visitors who have them"** (such as
> Algerian) are not sent with your page. They show for visitors whose device
> already has that font, and everyone else sees a similar fallback. Fonts under
> "Web fonts — look the same for every visitor" always look the same. If you
> need your website to look identical to everyone, choose a web font.

---

# 13. SUBSCRIPTION AND PLANS

## 13.1 — Registered and paid, but the school is not activated

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

**Activation is not automatic.** Every payment is checked by a person at
ScholarNest. Until they approve it, your administrative features stay locked.

```
Did you complete the plan selection?
   NO  → Finish it. Choose the plan and the number of pupil places.
   YES ↓
Did you upload proof of payment?
   NO  → Upload the receipt. Nothing happens until you do.
   YES ↓
Was the receipt accepted when you uploaded it?
   NO — you were told it did not look like a receipt, or the amount was short
       → Go to 14.2.
   YES ↓
Does your dashboard say the subscription is under review?
   YES → It is with the ScholarNest Team. Allow reasonable time.
   NO  → Escalate.
```

**While waiting:** you can sign in, but content features stay locked. This is
expected.

**Send ScholarNest:** school name, plan chosen, number of pupil places, date and
time of transfer, the amount, and confirmation that you uploaded the receipt.
**Never send card details or bank passwords.**

---

## 13.2 — "This feature requires the Standard or Exclusive plan"

**Affected:** School Admin, staff · **Plans:** Basic · **Severity:** 🟢 Low — working as intended

Not a fault. Your plan does not include that feature, and the block is enforced
by the server — the address will not work if typed directly.

The page names the feature and the plan that includes it. To get it, upgrade
through your subscription screen.

**Check the table in [The three facts](#1-your-plan-decides-what-exists) before
escalating** — most "missing feature" reports are plan limits.

---

## 13.3 — Wrong plan showing

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

1. Check your subscription screen — the plan and its status.
2. If it says pending, it has not been approved yet (13.1).
3. If it shows a plan you did not pay for, or fewer pupil places than you bought
   → escalate. Send what you paid, when, the receipt reference, and what the
   screen shows.

**Do not** pay again to fix it. That creates a duplicate payment to unpick.

---

## 13.4 — Subscription expired

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

Administrative features lock. **Your records are not deleted** — ScholarNest does
not delete a school's records because a subscription lapsed.

1. Renew from your subscription screen.
2. Pay and upload the receipt.
3. Wait for approval.
4. Access returns.

**If you are leaving ScholarNest**, ask for a copy of your data *before* asking for
anything to be deleted. Deletion is permanent and there is currently no
self-service export — allow time to arrange it.

---

# 14. PAYMENTS

## 14.1 — How paying actually works

**Affected:** School Admin · **Plans:** all · **Severity:** —

1. Choose your plan and pupil places.
2. **Transfer to the bank details shown.** Card payment appears in the interface
   but is **not yet working** — bank transfer is the only method that operates.
   ScholarNest never collects or stores card details.
3. Upload your receipt.
4. It is checked automatically for obvious problems — a screenshot that is not a
   receipt, an amount well below the price.
5. **A person then reviews it.** Passing the automatic check is not approval.
6. Approved → your school is activated.

> **When you upload a receipt, the file is sent to an automated document-reading
> service outside Nigeria to be checked.** Receipts often show a payer's name and
> bank details. If your school would rather that did not happen, contact the
> ScholarNest Team before uploading.

---

## 14.2 — Receipt was rejected

**Affected:** School Admin · **Plans:** all · **Severity:** 🟡 Medium

The message says why. In order of likelihood:

| Reason | What to do |
| --- | --- |
| It does not look like a payment receipt | Upload the actual receipt, not a screenshot of your banking home screen or a transfer confirmation with no detail |
| The amount is below what the plan costs | Check the price and the pupil places you chose. Pay the difference and upload a receipt showing the full amount |
| Unreadable | Retake it. Good light, whole document in frame, no fingers over the figures |

**File types:** a clear photograph (JPG/PNG) or a PDF from your bank. Under 5MB.

**Escalate if:** you paid the correct amount, the receipt is clear and correct,
and it is still refused. Send the receipt, the amount, and the date and time of
the transfer.

---

## 14.3 — Paid but nothing has happened

**Affected:** School Admin · **Plans:** all · **Severity:** 🟠 High

1. Check the money actually left your account. A pending transfer has not
   arrived.
2. Check you **uploaded the receipt**. Paying is not enough — nothing starts
   until the receipt is uploaded.
3. Check your subscription screen for the status.
4. If it says under review, it is with the ScholarNest Team.
5. **Do not pay again.**

---

## 14.4 — Paid twice by mistake

**Affected:** School Admin · **Plans:** all · **Severity:** 🟡 Medium

1. Do not submit another receipt.
2. Contact the ScholarNest Team with both transfer dates, both amounts and both
   references.
3. Only the ScholarNest Team can resolve this. Nothing in your dashboard can.

---

# 15. MESSAGES AND NOTIFICATIONS

## 15.1 — Not receiving notifications

**Affected:** anyone · **Plans:** all · **Severity:** 🟡 Medium

1. **Check inside ScholarNest first.** Notifications appear on the bell in your
   dashboard, not by email.
2. Reload the page — the bell updates on load, not continuously.
3. Check you are signed in as the right account. Notifications go to the account
   they concern.
4. School Admins: visitor messages come to your messages area (11.8).

---

## 15.2 — A notification opens the wrong page, or the message is empty

**Affected:** anyone · **Plans:** all · **Severity:** 🟢 Low

1. Reload and open it again.
2. Go to the feature directly instead — the message tells you where.
3. If it happens repeatedly, escalate with a screenshot of the notification
   (with any pupil's name covered) and where it took you.

---

# 16. FILE UPLOADS

## 16.1 — A file will not upload

**Affected:** anyone · **Plans:** all · **Severity:** 🟡 Medium

**Step 1 — Check the type.**

| Uploading | Accepted |
| --- | --- |
| Photographs, logos, gallery, hero | **JPG, PNG, WebP** |
| Payment receipts | JPG, PNG or PDF |
| CBT documents | Word (.docx) or PDF |
| Conduct report attachments | Photographs or short video, **max 4 files, 5MB each** |

**Not accepted anywhere: SVG.** It can carry hidden code, so ScholarNest refuses it
on purpose.

**iPhone HEIC photographs are not accepted.** Set your camera to "Most
Compatible", or convert the file first.

**Step 2 — Check the size.** Large photographs from a modern phone are often too
big. Resize before uploading.

**Step 3 — Rename it.** Remove accents, `#`, `&` and emoji from the file name.

**Step 4 — Try another browser** (section 19).

---

## 16.2 — Upload progress sticks

**Affected:** anyone · **Plans:** all · **Severity:** 🟡 Medium

1. **Wait.** A large PDF over school Wi-Fi is genuinely slow.
2. Do not close the tab or press the button again — a second attempt uploads it
   twice.
3. If it is still stuck after 5 minutes, reload and check whether it arrived
   before uploading again.
4. Try on a different network.

**For CBT documents specifically:** an upload that completes but stays at
"Waiting to start" is a different problem — see 8.1.

---

## 16.3 — A file uploaded but is not showing

**Affected:** anyone · **Plans:** all · **Severity:** 🟢 Low

1. **Ctrl+F5.** The browser is showing the old version.
2. Check in a private window.
3. Check on your phone.
4. Re-upload only if all three still show nothing.

---

# 17. MOBILE AND TABLET

Staff, student and parent portals are built for phones and tablets. The School
Admin dashboard is designed for a larger screen and is best used on a computer.

| Problem | Do this |
| --- | --- |
| Page runs off the side | Turn the device to landscape. Tables scroll sideways on their own — swipe **inside** the table |
| Buttons hard to tap | Zoom out to 100%. Landscape gives more room |
| Menu will not open | Reload. Check the browser is up to date |
| Signature pad not working | See 10.2 / 10.3. Landscape helps |
| Cannot upload from a phone | See 16.1 — usually HEIC or file size |
| Modal or pop-up will not close | Tap outside it, or reload |
| Page will not scroll | Close and reopen the tab |

**School Admin work — entering many results, editing the website, managing
subscriptions — should be done on a computer.** It is possible on a phone but
much slower, and the 3-minute timeout is less forgiving.

---

# 18. THE PLAN AND FEATURE CHECK

Before escalating **anything**, run this. It resolves a large share of reports.

```
1. Is the school ACTIVE with a current subscription?
      NO → Section 13. Nothing else will work.
2. Is the feature ON YOUR PLAN?
      NO → Not a fault. See the plan table at the top.
3. Is the person on the RIGHT PORTAL for their role?
      NO → Section 1.1.
4. Is the person's account ACTIVE?
      NO → Section 1.3.
5. For teachers — are they ASSIGNED to the class and subject?
      NO → Sections 2.2 / 2.3.
6. For results — is it ENTERED, COMPLETE and PUBLISHED?
      NO → Sections 4.3 / 4.5.
7. For a parent who cannot see a result — are FEES outstanding?
      YES → Section 4.6.
8. Have you tried Ctrl+F5 and a private window?
      NO → Section 19.
   ↓
Still broken → Escalate with the template in section 22.
```

---

# 19. BROWSER TROUBLESHOOTING

Work up the levels. Stop as soon as it works.

**Level 1 — Reload properly.**
**Ctrl+F5** (Windows) or **Cmd+Shift+R** (Mac). This fetches a fresh copy rather
than the stored one. It fixes most "I changed it and nothing happened" reports.

**Level 2 — Private/incognito window.**
Loads with no stored data. **If it works here, the problem is your browser's
stored copy, not ScholarNest.**

**Level 3 — Clear data for this site only.**
Browser settings → Privacy → site settings → find ScholarNest → clear.
**Do not clear all browsing data.** You will sign yourself out of everything
else for no reason.

**Level 4 — Another browser.** Chrome, Edge, Firefox or Safari, kept up to date.

**Level 5 — Another device or network.** Try a phone on mobile data. If it works
there, your school network or that computer is the problem — not ScholarNest.

**Level 6 — Escalate**, saying which levels you tried and what happened at each.

---

# 20. SECURITY INCIDENTS

**🔴 Read this before doing anything else in this section.**

## The rule

```
STOP  →  SECURE THE ACCOUNT  →  DO NOT DELETE ANYTHING  →  CONTACT SCHOLARNEST
```

**Do not investigate it yourself.** Trying to reproduce a security problem can
destroy the record of what happened and can make it worse.

**Do not delete anything** — not the message, not the file, not the account, not
the record. It is the evidence.

## 20.1 — When to treat something as a security incident

- Someone saw a pupil's information they should not have
- A parent saw a child who is not theirs
- A teacher saw a class that is not theirs
- **Anything belonging to another school appeared**
- An account was used by someone who should not have it
- A sign-in you cannot account for
- A password was written somewhere others could see it
- Records changed and nobody will say by whom
- A suspicious file or message inside ScholarNest

## 20.2 — What to do, in order

1. **Stop using the affected account.** Do not keep clicking to see what else
   shows.
2. **Change the password** if it is your own account, or have the School Admin
   reset it if not.
3. **Deactivate the account** if you think someone else has it.
4. **Screenshot what you saw.** Cover any child's name and photograph before
   sharing outside your school.
5. **Write down**: what was seen, who saw it, when, which page, what they were
   doing.
6. **Contact the ScholarNest Team and say "security incident".** That routes it
   properly.
7. **Do not delete anything.**
8. **Tell your school's data protection lead.** Your school — not ScholarNest — is
   the one who must notify parents if pupils' information was disclosed.

## 20.3 — Compromised account

1. Reset that password immediately.
2. Deactivate the account if you are unsure who has it.
3. Check what that account could reach — a School Admin account reaches every
   pupil record in the school.
4. Contact the ScholarNest Team. They can see when the account was used and from
   where.
5. Do not delete the account. It is the record.

## 20.4 — A result token was shared publicly

1. Tell the ScholarNest Team, who can revoke it.
2. Ask your School Admin to check the token's access log — you can see how often
   and when it was used.
3. Issue a fresh token to the family.

**A token only ever exposes one pupil's result for one examination.** It gives
no access to any portal, any other pupil or any other examination. Serious, but
contained.

## 20.5 — What ScholarNest will never ask you for

The ScholarNest Team will **never** ask for:

- Your password, or anyone else's
- A result token
- Bank or card details
- Pupils' records "for testing"

**A message asking for any of these is not from ScholarNest.** Do not reply. Report
it as a security incident.

---

# 21. WHO FIXES WHAT

## Your school can fix

Wording and content on your website · uploading and replacing images · adding
and editing pupils, staff and guardians · teacher assignments · classes and
class names · grade bands · entering, correcting and publishing results ·
attendance and corrections · issuing result tokens · releasing withheld results ·
resetting passwords for your own users · deactivating and reactivating accounts ·
drawing and replacing signatures · brand colour, fonts and website settings ·
deactivating pupils to free capacity

## Only the ScholarNest Team can fix

Approving a subscription or a capacity top-up · verifying or refunding a payment ·
restarting the background service that reads CBT documents · anything affecting
more than one school · a security incident · recovering deleted data · a plan
entitlement that is wrong after payment · revoking a token across the platform ·
anything where ScholarNest itself is down or erroring

## Escalate immediately, without troubleshooting

🔴 Any security incident · data belonging to another school appearing ·
accidental deletion of a pupil or staff member · a result published to the wrong
family · ScholarNest completely unreachable

---

# 22. CONTACTING THE SCHOLARNEST TEAM

## Send this

| | |
| --- | --- |
| **School name** | Exactly as registered |
| **Your name and role** | School Admin, teacher, etc. |
| **Who is affected** | Their role, and their staff or admission number |
| **How many** | One person, or several? This changes everything |
| **Where** | Which page or feature |
| **What happens** | Exactly what you see, word for word |
| **Exact message** | Copy the wording, do not paraphrase |
| **When it started** | Date and time |
| **What you have tried** | Which steps from this book, and what happened |
| **Browser and device** | "Chrome on Windows 11", "Safari on iPhone" |
| **Screenshot** | With any pupil's name and photograph covered |

## Never send

❌ Passwords — anyone's, ever
❌ Result tokens
❌ Bank or card details
❌ Full pupil records when an admission number would do
❌ Photographs of children unless specifically asked, and then only through a
   method your school considers secure

## Severity to state

| Say | When |
| --- | --- |
| 🔴 **Critical** | Security, data loss, nobody can use ScholarNest |
| 🟠 **High** | A whole feature down for the whole school |
| 🟡 **Medium** | One person or one feature, workaround exists |
| 🟢 **Low** | Cosmetic or content |

**Contact:** [TO BE PROVIDED]

---

# 23. ERROR MESSAGES

| What you see | What it means | Do this |
| --- | --- | --- |
| **These credentials do not match our records** | Wrong details, or wrong portal | 1.1 |
| **Too many login attempts… try again in N seconds** | 5 failed attempts. Locked 15 minutes | 1.2 — wait |
| **This account has been deactivated. Please contact your school.** | The school switched it off | 1.3 |
| **Your session expired while this page was open** | Page open too long. Your typing is kept | 1.6 — submit again |
| **You must change your password before continuing** | Temporary password must be replaced | 1.5 |
| **Page not found (404)** | Wrong or altered address, or the item was deleted | 1.7 — use the exact link |
| **This feature requires the Standard or Exclusive plan** | Not on your plan. Enforced by the server | 13.2 |
| **Student capacity reached** | Every pupil place is in use | 3.5 |
| **This examination has no subjects yet** | Nothing to score | 4.2 |
| **No score has been recorded for [subjects]** | Those teachers have not entered marks | 4.5 |
| **Both the test and examination marks are needed for [subject]** | One of the two is missing | 4.5 |
| **There is no class teacher remark on this result** | A warning, not a refusal — you can publish | 4.5 |
| **This test cannot be locked or unlocked — students have already started it** | Deliberate. Archive it instead | 8.6 |
| **Extraction failed** | The document could not be read | 8.2 |
| **Waiting to start… (stuck)** | Background service not running | 8.1 |
| **Needs exam body and subject** | Read successfully; choose what it belongs to | 8.3 |
| **That is not a signature image** | The pad produced nothing usable | 10.4 |
| **Access denied (403)** | Not permitted, or not on your plan | 18, then escalate |
| **Too many requests (429)** | Rate limited for safety | Wait, then try once |
| **Server error (500)** | A fault at ScholarNest's end. **Not your fault** | Note the time and escalate |

---

# 24. QUICK REFERENCE

| Problem | First | Second | Escalate when |
| --- | --- | --- | --- |
| Cannot log in | Right portal? Right details? | School Admin resets password | Several people affected |
| Locked out | Wait 15 minutes | Confirm details, try once | Locked without wrong attempts (security) |
| Logged out constantly | Normal — 3-minute timeout | Save more often | Logged out while actively working |
| Teacher sees no classes | Check assignments | Add Class/Subject Teacher | Assignments exist but nothing shows |
| Cannot enter scores | Check subject assignment | Check the examination has that subject | Boxes present but will not save |
| Cannot publish result | Read what the message names | Those teachers enter the marks | Everything entered, still refused |
| Parent cannot see result | **Check unpaid fees** | Check published; check linked | All correct, still hidden |
| Token not working | Right pupil + right exam? | Uses left? Issue a new one | Fresh token also fails |
| Cannot add a pupil | Deactivate leavers | Buy more capacity | Capacity shows wrong after paying |
| Payment not reflected | Receipt uploaded? | Check subscription status | Under review too long |
| Website not loading | Published? Ctrl+F5 | Private window; another device | Fails in private window too |
| Change not showing | Ctrl+F5 | Private window | Still old in a private window |
| Image not showing | Check type (JPG/PNG/WebP) | Re-upload; Ctrl+F5 | Re-upload also fails |
| CBT stuck "Waiting" | Wait 5 min; do **not** re-upload | Wait 15 min | Still waiting — ScholarNest restarts it |
| Signature missing | Has the person drawn one? | Right class teacher assigned? Reprint | Drawn and assigned, still absent |
| Contact form silent | Check messages **in ScholarNest** | Send yourself a test | Your own test does not arrive |
| Saw another school's data | **STOP** | Screenshot, do not delete | 🔴 **Immediately** |

---

*This runbook describes ScholarNest as it behaved on 1 September 2026. If something
in it no longer matches what you see, tell the ScholarNest Team — a runbook that is
wrong is worse than no runbook.*
