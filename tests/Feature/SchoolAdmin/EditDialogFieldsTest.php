<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Support\AlpineModel;

/*
 * The Add/Edit dialogs share one form, so their fields are written as
 * x-model="editing ? editing.first_name : ''". Alpine cannot assign to a
 * ternary, so the fields threw "Invalid left-hand side in assignment" and
 * the Edit dialog did not reliably show the record being edited. The field
 * components now bind such expressions one way, and only plain properties
 * keep a two-way x-model.
 */

test('plain properties are assignable and expressions are not', function (string $expression, bool $assignable) {
    expect(AlpineModel::isAssignable($expression))->toBe($assignable);
})->with([
    'a property' => ['name', true],
    'a nested property' => ['form.first_name', true],
    'an index' => ["form['first_name']", true],
    'a ternary' => ["editing ? editing.first_name : ''", false],
    'a fallback' => ["editing?.name ?? ''", false],
    'a call' => ['getName()', false],
]);

test('the staff Edit dialog fields follow the record being edited', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school);
    Staff::factory()->create(['school_id' => $school->id]);

    $html = $this->actingAs($admin)->get(route('staff.index'))->assertOk()->getContent();

    expect($html)
        ->not->toContain('x-model="editing ?')
        ->toContain('x-effect="const __v = ((editing ? editing.first_name');
});
