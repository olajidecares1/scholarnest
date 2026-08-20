<?php

use App\Enums\IdCardHolderType;
use App\Enums\IssuedIdCardStatus;
use App\Models\IssuedIdCard;
use App\Models\School;
use App\Models\Student;

test('an active ID card can be verified without authentication', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    $card = IssuedIdCard::factory()->create([
        'school_id' => $school->id,
        'holder_type' => IdCardHolderType::Student,
        'holder_uuid' => $student->uuid,
        'card_number' => 'SCH-STU-000001',
        'status' => IssuedIdCardStatus::Active,
    ]);

    $this->get(route('id-verify.show', $card))
        ->assertOk()
        ->assertSee('Valid ID Card')
        ->assertSee('Amaka Obi')
        ->assertSee('SCH-STU-000001');
});

test('a revoked ID card shows as invalid on the verification page', function () {
    $school = School::factory()->create();
    $card = IssuedIdCard::factory()->create([
        'school_id' => $school->id,
        'status' => IssuedIdCardStatus::Revoked,
    ]);

    $this->get(route('id-verify.show', $card))
        ->assertOk()
        ->assertSee('revoked');
});

test('an unknown ID card uuid returns a 404', function () {
    $this->get('/id-verify/00000000-0000-0000-0000-000000000000')->assertNotFound();
});
