<?php

use App\Models\School;
use Illuminate\Database\QueryException;

test('a new school is automatically given a unique school code', function () {
    $school = School::factory()->create(['school_code' => null]);

    expect($school->school_code)->not->toBeNull();
    expect(strlen($school->school_code))->toBeGreaterThanOrEqual(6);
});

test('a school code cannot be duplicated at the database level', function () {
    School::factory()->create(['school_code' => 'DUPLICATE']);

    expect(fn () => School::factory()->create(['school_code' => 'DUPLICATE']))
        ->toThrow(QueryException::class);
});

test('an explicitly provided school code is not overwritten by auto-generation', function () {
    $school = School::factory()->create(['school_code' => 'MYCODE']);

    expect($school->school_code)->toBe('MYCODE');
});
