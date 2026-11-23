<?php

use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\ResultTokenAccessOutcome;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultCheckingPinUsage;
use App\Models\ResultTokenAccessLog;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Deleting issued result tokens from the Issued tokens list: one at a time,
 * the ticked ones together, or every token in a category (class, result,
 * session, term, status, student), always inside the admin's own school.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Basic);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->jss1First = Examination::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 1', 'term' => 'first', 'session' => '2026/2027',
    ]);
    $this->jss2First = Examination::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 2', 'term' => 'first', 'session' => '2026/2027',
    ]);
    $this->jss1Second = Examination::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 1', 'term' => 'second', 'session' => '2026/2027',
    ]);
});

function tokensFor(object $test, Examination $examination, int $count, array $state = [], array $student = []): Collection
{
    return collect(range(1, $count))->map(fn () => ResultCheckingPin::factory()->create(array_merge([
        'school_id' => $test->school->id,
        'examination_id' => $examination->id,
        'bound_student_id' => Student::factory()->create(array_merge([
            'school_id' => $test->school->id,
            'class_name' => $examination->class_name,
        ], $student))->id,
        'generated_by' => $test->admin->id,
    ], $state)));
}

test('the issued tokens list offers a checkbox, a delete button and category filters', function () {
    $token = tokensFor($this, $this->jss1First, 1)->first();

    $this->actingAs($this->admin)
        ->get(route('result-pins.index'))
        ->assertOk()
        ->assertSee('value="'.$token->uuid.'"', false)
        ->assertSee(route('result-pins.destroy', $token), false)
        ->assertSee('name="class_name"', false)
        ->assertSee('name="term"', false)
        ->assertSee('name="search"', false)
        // No category chosen, so no "delete all matching" yet.
        ->assertDontSee('matching</button>', false);
});

test('filtering by class and term narrows the list and offers delete all matching', function () {
    tokensFor($this, $this->jss1First, 2);
    tokensFor($this, $this->jss2First, 3);
    tokensFor($this, $this->jss1Second, 4);

    $this->actingAs($this->admin)
        ->get(route('result-pins.index', ['class_name' => 'JSS 1', 'term' => 'first']))
        ->assertOk()
        ->assertSee('Delete all 2 matching')
        ->assertSee(route('result-pins.destroy-matching'), false);
});

test('a single token can be deleted and the deletion is audited', function () {
    $token = tokensFor($this, $this->jss1First, 1)->first();

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy', $token))
        ->assertRedirect();

    expect(ResultCheckingPin::find($token->id))->toBeNull()
        ->and(AuditLog::where('action', 'result-token.deleted')->exists())->toBeTrue();
});

test('ticked tokens are deleted together and nothing else is', function () {
    $tokens = tokensFor($this, $this->jss1First, 4);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.bulk-destroy'), ['tokens' => $tokens->take(3)->pluck('uuid')->all()])
        ->assertRedirect(route('result-pins.index'))
        ->assertSessionHas('status', fn ($status) => str_contains($status, '3 tokens were deleted'));

    expect(ResultCheckingPin::whereIn('id', $tokens->pluck('id'))->pluck('id')->all())->toBe([$tokens->last()->id]);

    $log = AuditLog::where('action', 'result-token.bulk-deleted')->sole();
    expect($log->description)->toContain('Deleted 3 result token(s)')
        ->and($log->school_id)->toBe($this->school->id);
});

test('another school token cannot be deleted, singly or in bulk', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Basic);
    $foreign = ResultCheckingPin::factory()->create(['school_id' => $other->id]);
    $mine = tokensFor($this, $this->jss1First, 1)->first();

    $this->actingAs($this->admin)->delete(route('result-pins.destroy', $foreign))->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('result-pins.bulk-destroy'), ['tokens' => [$foreign->uuid, $mine->uuid]])
        ->assertRedirect();

    expect(ResultCheckingPin::find($foreign->id))->not->toBeNull()
        ->and(ResultCheckingPin::find($mine->id))->toBeNull();

    // Only foreign tokens ticked: nothing found, nothing deleted.
    $this->actingAs($this->admin)
        ->delete(route('result-pins.bulk-destroy'), ['tokens' => [$foreign->uuid]])
        ->assertSessionHasErrors('tokens');

    expect(ResultCheckingPin::find($foreign->id))->not->toBeNull();
});

test('bulk delete needs at least one ticked token', function () {
    $this->actingAs($this->admin)
        ->delete(route('result-pins.bulk-destroy'), ['tokens' => []])
        ->assertSessionHasErrors('tokens');
});

test('every token in a class is deleted across all pages, leaving other classes alone', function () {
    $jss1 = tokensFor($this, $this->jss1First, 25)->concat(tokensFor($this, $this->jss1Second, 2));
    $jss2 = tokensFor($this, $this->jss2First, 3);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['class_name' => 'JSS 1', 'expected_count' => 27])
        ->assertRedirect(route('result-pins.index'))
        ->assertSessionHas('status', fn ($status) => str_contains($status, '27 tokens were deleted') && str_contains($status, 'class: JSS 1'));

    expect(ResultCheckingPin::whereIn('id', $jss1->pluck('id'))->count())->toBe(0)
        ->and(ResultCheckingPin::whereIn('id', $jss2->pluck('id'))->count())->toBe(3);
});

test('every token for one result, term or status can be deleted', function () {
    tokensFor($this, $this->jss1First, 2);
    $second = tokensFor($this, $this->jss1Second, 3);
    $revoked = tokensFor($this, $this->jss2First, 2, ['status' => ResultCheckingPinStatus::Revoked]);
    $active = tokensFor($this, $this->jss2First, 1);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['status' => 'revoked', 'expected_count' => 2])
        ->assertSessionHasNoErrors();
    expect(ResultCheckingPin::whereIn('id', $revoked->pluck('id'))->count())->toBe(0)
        ->and(ResultCheckingPin::find($active->first()->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['term' => 'second', 'expected_count' => 3])
        ->assertSessionHasNoErrors();
    expect(ResultCheckingPin::whereIn('id', $second->pluck('id'))->count())->toBe(0);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['examination_id' => $this->jss1First->id, 'expected_count' => 2])
        ->assertSessionHasNoErrors();

    expect(ResultCheckingPin::forSchool($this->school)->count())->toBe(1);
});

test('every token for one student can be deleted by name or admission number', function () {
    $ada = tokensFor($this, $this->jss1First, 1, [], ['first_name' => 'Ada', 'last_name' => 'Okafor', 'admission_number' => 'AKN/001'])->first();
    $adaStudent = $ada->boundStudent;
    ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => $this->jss1Second->id,
        'bound_student_id' => $adaStudent->id,
    ]);
    $other = tokensFor($this, $this->jss1First, 1, [], ['first_name' => 'Bola', 'last_name' => 'Adeyemi'])->first();

    $this->actingAs($this->admin)
        ->get(route('result-pins.index', ['search' => 'Ada Okafor']))
        ->assertSee('Delete all 2 matching');

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['search' => 'AKN/001', 'expected_count' => 2])
        ->assertSessionHasNoErrors();

    expect(ResultCheckingPin::where('bound_student_id', $adaStudent->id)->count())->toBe(0)
        ->and(ResultCheckingPin::find($other->id))->not->toBeNull();
});

test('delete all matching refuses when no category is chosen', function () {
    tokensFor($this, $this->jss1First, 3);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['expected_count' => 3])
        ->assertSessionHasErrors('tokens');

    expect(ResultCheckingPin::forSchool($this->school)->count())->toBe(3);
});

test('delete all matching refuses when the set changed since the page loaded', function () {
    tokensFor($this, $this->jss1First, 3);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['class_name' => 'JSS 1', 'expected_count' => 2])
        ->assertSessionHasErrors('tokens');

    expect(ResultCheckingPin::forSchool($this->school)->count())->toBe(3);
});

test('delete all matching never reaches another school tokens', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Basic);
    $foreignExam = Examination::factory()->create(['school_id' => $other->id, 'class_name' => 'JSS 1', 'term' => 'first', 'session' => '2026/2027']);
    $foreign = ResultCheckingPin::factory()->create(['school_id' => $other->id, 'examination_id' => $foreignExam->id]);
    tokensFor($this, $this->jss1First, 2);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.destroy-matching'), ['class_name' => 'JSS 1', 'term' => 'first', 'expected_count' => 2])
        ->assertSessionHasNoErrors();

    expect(ResultCheckingPin::find($foreign->id))->not->toBeNull();
});

test('deleting tokens removes their usage rows but keeps the access history', function () {
    $token = tokensFor($this, $this->jss1First, 1)->first();

    ResultCheckingPinUsage::create([
        'result_checking_pin_id' => $token->id,
        'student_id' => $token->bound_student_id,
        'examination_id' => $token->examination_id,
        'used_at' => now(),
    ]);
    $log = ResultTokenAccessLog::create([
        'school_id' => $this->school->id,
        'result_checking_pin_id' => $token->id,
        'student_id' => $token->bound_student_id,
        'examination_id' => $token->examination_id,
        'outcome' => ResultTokenAccessOutcome::cases()[0],
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('result-pins.bulk-destroy'), ['tokens' => [$token->uuid]])
        ->assertSessionHasNoErrors();

    expect(ResultCheckingPinUsage::where('result_checking_pin_id', $token->id)->exists())->toBeFalse()
        ->and($log->fresh())->not->toBeNull()
        ->and($log->fresh()->result_checking_pin_id)->toBeNull();
});

test('only school admins can bulk delete tokens', function () {
    $token = tokensFor($this, $this->jss1First, 1)->first();
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)
        ->delete(route('result-pins.bulk-destroy'), ['tokens' => [$token->uuid]]);

    expect(ResultCheckingPin::find($token->id))->not->toBeNull();
});
