# 2. Read a past-question PDF twice and keep the better reading

- **Status:** Accepted, 16 September 2026
- **Decided by:** Olajide

## The problem

Uploading real JAMB past-question compilations produced questions nobody could
sit. A Mathematics compilation of 1983–2004 came out as 867 "questions", 152 of
them with no options at all and most of the rest with the wrong ones. Question 1
read:

```
If M represents the median and D the mode of the
measurements 5, 9, 3, 5, 8 then (M,D) is
```

with zero options, while question 10's text had been folded into the middle of
it.

The cause is not the parser. Asking a PDF for "its text" returns the text in the
order the *file* stores it, and these papers are printed in two columns. The
stored order braids the columns together:

```
1. If M represents the median and D the mode
10. If x + 2 and x – 1 are factors of
measurements 5, 9, 3, 5, 8 then (M,D) is
2kx² + 24, find the values of l and k
```

No parser downstream can recover question 1 from that, and every compilation
anyone uploads is printed this way.

## What was done

`PdfLayoutReader` takes each run of text with the position it is drawn at
(`getDataTm()`), finds the gutter down the middle of the page, and reads each
column top to bottom on its own. A running head printed across both columns
("Mathematics 1983") is kept whole, because splitting it files every question on
the page under no year at all. Raised text is folded back into the line it
belongs to, so `x²` is `x²` and not a line containing `2`.

The gutter is found once for the whole document, from where runs start: the
widest quiet strip in the middle of the page, taking its *far* side, since a
line of the first column may reach into the strip while nothing starts there.
A candidate is rejected when text runs straight through it, which is what tells
a real gutter from a page that merely happens to start few words near its
middle.

## Why both readings, and not just the better algorithm

The printed-order reading is right for the body of a paper. It is not always
right for the answer key, which these compilations set in four narrow columns,
and it is not needed at all for a single-column paper.

Which one a document suits cannot be told from the text. So the PDF is read both
ways and both are parsed, and the reading that produced the better questions is
the one used. "Better" is counted in what a student needs: a question with a
full set of lettered options scores one, and one that also has its answer scores
three, so a reading that produces more questions by cutting them in half loses
to one that produces fewer whole ones. See `LocalQuestionExtractor::score()`.

## What it costs

Reading a 65-page, 3MB compilation twice takes about thirteen minutes on a
laptop. The extraction jobs therefore declare a 1800-second timeout and
`queue.connections.database.retry_after` is 1900, so a long read is never handed
to a second worker while the first is still going. `CbtExtractionRunner` waits 35
minutes before calling an upload interrupted.

This is a background job that runs once per uploaded document, so the time buys
questions that are usable rather than questions a person has to retype.

## What it produced

Measured on the three documents that prompted this, before and after:

| Paper | Questions | Years | With answers | Four options |
|---|---|---|---|---|
| Literature in English (.docx) | 431 | 2010–2018 | 429 | 390 |
| Use of English (.pdf, two columns) | 784 | 2010–2018 | 729 | 684 |
| Mathematics 1983–2004 (.pdf, two columns) | 714 | 21 years | 0 | 455 + 81 with five |

The Mathematics compilation prints no answer key at all, so every one of its
questions is flagged for review and none can be published until the AkademicNest
Team supplies the answers. That is the designed outcome, not a failure of the
reader: a question whose answer nobody knows cannot mark a student.

These numbers are asserted as floors in
`tests/Feature/DocumentExtraction/JambPastQuestionTest.php`, which runs against
the documents themselves. The papers are not in the repository, so see the note
at the top of that file for how to run it.
