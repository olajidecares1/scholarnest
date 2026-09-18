<?php

use App\Models\FeePayment;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Services\ResultAccessPolicy;

/**
 * What a student still owes, asked of the database.
 *
 * THIS IS THE QUERY THAT 500'd EVERY RESULT PAGE IN PRODUCTION. It clamped
 * each invoice at zero with "MAX(amount - paid, 0)", which is SQLite's
 * two-argument MAX() and nothing else's: MySQL has MAX() as an aggregate
 * taking one argument, so the statement was a syntax error on every real
 * request. The suite ran on SQLite and passed.
 *
 * These tests are about the answer rather than the spelling, so they hold on
 * either database; the guard against the spelling is the MySQL job in CI.
 */
function studentOwing(School $school, array $invoices): Student
{
    $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    foreach ($invoices as [$amount, $paid]) {
        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'amount' => $amount,
        ]);

        if ($paid > 0) {
            FeePayment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $paid]);
        }
    }

    return $student;
}

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create());
    $this->policy = app(ResultAccessPolicy::class);
});

test('an unpaid invoice is owed in full', function () {
    $student = studentOwing($this->school, [[50000, 0]]);

    expect($this->policy->outstandingBalances([$student->id])[$student->id])->toBe(50000.0);
});

test('a part payment leaves the remainder', function () {
    $student = studentOwing($this->school, [[50000, 20000]]);

    expect($this->policy->outstandingBalances([$student->id])[$student->id])->toBe(30000.0);
});

test('an overpaid invoice cannot pay off another one', function () {
    // The clamp, and the reason there is one. Without it the 10,000 overpaid
    // on the first invoice would cancel part of the second, and a result
    // would be released to a family that still owes for it.
    $student = studentOwing($this->school, [[20000, 30000], [40000, 0]]);

    expect($this->policy->outstandingBalances([$student->id])[$student->id])->toBe(40000.0);
});

test('a fully settled student owes nothing', function () {
    $student = studentOwing($this->school, [[25000, 25000], [15000, 15000]]);

    expect($this->policy->outstandingBalances([$student->id])[$student->id])->toBe(0.0);
});

test('a whole class is answered in one query, each student on their own', function () {
    $owes = studentOwing($this->school, [[30000, 0]]);
    $settled = studentOwing($this->school, [[30000, 30000]]);

    $balances = $this->policy->outstandingBalances([$owes->id, $settled->id]);

    expect($balances[$owes->id])->toBe(30000.0)
        ->and($balances[$settled->id])->toBe(0.0);
});

test('a student with no invoices at all is simply absent', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);

    expect($this->policy->outstandingBalances([$student->id]))->not->toHaveKey($student->id);
});

test('asking about nobody asks the database nothing', function () {
    expect($this->policy->outstandingBalances([]))->toBe([]);
});
