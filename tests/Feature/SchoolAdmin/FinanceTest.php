<?php

use App\Enums\FeePaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin can create a fee structure', function () {
    $response = $this->actingAs($this->admin)->post(route('finance.store'), [
        'name' => 'Tuition Fee',
        'amount' => 50000,
        'session' => '2025/2026',
    ]);

    $response->assertRedirect();

    $structure = FeeStructure::where('name', 'Tuition Fee')->firstOrFail();
    expect($structure->school_id)->toBe($this->school->id);
});

test('generating invoices creates one per matching active student and skips existing ones', function () {
    $structure = FeeStructure::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'amount' => 20000]);
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SS 1', 'is_active' => true]);

    $this->actingAs($this->admin)->post(route('finance.generate', $structure))->assertRedirect();

    expect(Invoice::where('fee_structure_id', $structure->id)->count())->toBe(2);

    $this->actingAs($this->admin)->post(route('finance.generate', $structure));
    expect(Invoice::where('fee_structure_id', $structure->id)->count())->toBe(2);
});

test('a school admin can create an ad-hoc invoice', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('finance.invoices.store'), [
        'student_id' => $student->id,
        'title' => 'Exam Fee',
        'amount' => 5000,
    ])->assertRedirect();

    $invoice = Invoice::where('student_id', $student->id)->firstOrFail();
    expect($invoice->title)->toBe('Exam Fee');
    expect($invoice->status())->toBe('Unpaid');
});

test('recording a payment updates the invoice balance and status', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $invoice = Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => 10000]);

    $this->actingAs($this->admin)->post(route('finance.invoices.payments.store', $invoice), [
        'amount' => 4000,
        'paid_at' => now()->toDateString(),
        'method' => FeePaymentMethod::Cash->value,
    ])->assertRedirect();

    expect($invoice->fresh()->amountPaid())->toBe(4000.0);
    expect($invoice->fresh()->balance())->toBe(6000.0);
    expect($invoice->fresh()->status())->toBe('Partial');

    $this->actingAs($this->admin)->post(route('finance.invoices.payments.store', $invoice), [
        'amount' => 6000,
        'paid_at' => now()->toDateString(),
        'method' => FeePaymentMethod::BankTransfer->value,
    ]);

    expect($invoice->fresh()->status())->toBe('Paid');
});

test('a payment cannot exceed the remaining balance', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $invoice = Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => 10000]);

    $this->actingAs($this->admin)->post(route('finance.invoices.payments.store', $invoice), [
        'amount' => 15000,
        'paid_at' => now()->toDateString(),
        'method' => FeePaymentMethod::Cash->value,
    ])->assertSessionHasErrors('amount');
});

test('a school admin cannot record a payment on another school\'s invoice', function () {
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);
    $invoice = Invoice::factory()->create(['school_id' => $otherSchool->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)->post(route('finance.invoices.payments.store', $invoice), [
        'amount' => 100,
        'paid_at' => now()->toDateString(),
        'method' => FeePaymentMethod::Cash->value,
    ])->assertForbidden();
});

test('an invoice with payments cannot be deleted', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $invoice = Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => 10000]);
    $invoice->payments()->create(['amount' => 1000, 'paid_at' => now(), 'method' => FeePaymentMethod::Cash]);

    $this->actingAs($this->admin)->delete(route('finance.invoices.destroy', $invoice));

    expect(Invoice::find($invoice->id))->not->toBeNull();
});

test('the dashboard shows outstanding fees and collection percentage', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $invoice = Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => 10000]);
    $invoice->payments()->create(['amount' => 2500, 'paid_at' => now(), 'method' => FeePaymentMethod::Cash]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSee('25%')
        ->assertSee('7,500');
});
