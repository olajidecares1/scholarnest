<?php

use App\Enums\IdCardHolderType;
use App\Enums\IssuedIdCardStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\IdCardTemplate;
use App\Models\IdentifierChange;
use App\Models\IssuedIdCard;
use App\Models\Plan;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| ID Card Management: students by class or admission number, staff by list
| or Staff ID; generate, preview and print one or many; and the permanent,
| unique admission numbers and Staff IDs every card hangs on.
*/

function idCardManagementAdmin(): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

beforeEach(function () {
    $this->admin = idCardManagementAdmin();
    $this->school = $this->admin->school;

    foreach ([IdCardHolderType::Student, IdCardHolderType::TeachingStaff, IdCardHolderType::NonTeachingStaff] as $type) {
        IdCardTemplate::factory()->create(['school_id' => $this->school->id, 'type' => $type, 'is_default' => true]);
    }

    $this->jss1 = collect(['Ada Obi', 'Bola Ade', 'Chidi Eze'])->map(fn (string $name, int $i) => Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
        'first_name' => explode(' ', $name)[0],
        'last_name' => explode(' ', $name)[1],
        'admission_number' => sprintf('GLA-JUR-%03d', $i + 1),
    ]));

    $this->jss2Student = Student::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true,
        'first_name' => 'Dayo', 'last_name' => 'Ola', 'admission_number' => 'GLA-JUR-050',
    ]);
});

function idCardsFor(School $school): Collection
{
    return IssuedIdCard::where('school_id', $school->id)->get();
}

// ---------------------------------------------------------------------------
// Students
// ---------------------------------------------------------------------------

test('choosing a class lists every student in it, and only them', function () {
    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['tab' => 'student', 'class' => 'JSS 1']))
        ->assertOk()
        ->assertSee('Ada Obi')->assertSee('Bola Ade')->assertSee('Chidi Eze')
        ->assertSee('GLA-JUR-001')
        ->assertDontSee('Dayo Ola')
        ->assertSee('Generate all')
        ->assertSee('Not generated');
});

test('before a class is chosen, no student list is loaded', function () {
    $this->actingAs($this->admin)
        ->get(route('id-cards.index'))
        ->assertOk()
        ->assertSee('Choose a class, or find one student by admission number')
        ->assertDontSee('Ada Obi');
});

test('cards are generated in bulk for a whole class, each linked to its student', function () {
    $this->actingAs($this->admin)
        ->from(route('id-cards.index', ['class' => 'JSS 1']))
        ->post(route('id-cards.generate'), ['type' => 'student', 'records' => $this->jss1->pluck('uuid')->all()])
        ->assertRedirect(route('id-cards.index', ['class' => 'JSS 1']))
        ->assertSessionHas('status', fn ($status) => str_contains($status, '3 ID cards ready (3 new)'));

    $cards = idCardsFor($this->school);
    expect($cards)->toHaveCount(3)
        ->and($cards->pluck('holder_uuid')->sort()->values()->all())->toBe($this->jss1->pluck('uuid')->sort()->values()->all())
        ->and($cards->every(fn ($card) => $card->holder()?->is($this->jss1->firstWhere('uuid', $card->holder_uuid))))->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['class' => 'JSS 1']))
        ->assertSee($cards->first()->card_number)
        ->assertDontSee('Not generated');
});

test('generating again never makes a second card: everyone keeps theirs', function () {
    $records = $this->jss1->pluck('uuid')->all();

    $this->actingAs($this->admin)->post(route('id-cards.generate'), ['type' => 'student', 'records' => $records]);
    $numbers = idCardsFor($this->school)->pluck('card_number', 'holder_uuid');

    $this->actingAs($this->admin)
        ->post(route('id-cards.generate'), ['type' => 'student', 'records' => $records])
        ->assertSessionHas('status', fn ($status) => str_contains($status, '3 already had a card and kept it'));

    expect(idCardsFor($this->school))->toHaveCount(3)
        ->and(idCardsFor($this->school)->pluck('card_number', 'holder_uuid')->all())->toBe($numbers->all());
});

test('cards can be generated for only the students ticked', function () {
    $this->actingAs($this->admin)
        ->post(route('id-cards.generate'), ['type' => 'student', 'records' => [$this->jss1[1]->uuid]]);

    expect(idCardsFor($this->school)->pluck('holder_uuid')->all())->toBe([$this->jss1[1]->uuid]);
});

test('a student is found by their exact admission number, whatever its capitals', function () {
    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['admission' => 'gla-jur-002']))
        ->assertOk()
        ->assertSee('Bola Ade')
        ->assertSee('Admission number GLA-JUR-002')
        ->assertDontSee('Ada Obi');
});

test('an old admission number still finds the student, saying what it is now', function () {
    IdentifierChange::create([
        'school_id' => $this->school->id, 'type' => 'admission', 'holder_id' => $this->jss1[0]->id,
        'old_value' => 'OLD-777', 'new_value' => 'GLA-JUR-001', 'batch' => (string) Str::uuid(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['admission' => 'OLD-777']))
        ->assertSee("OLD-777 was Ada Obi's old admission number. It is now GLA-JUR-001.")
        ->assertSee('Ada Obi');
});

test('a part of a number shows the closest admission numbers, and none says so', function () {
    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['admission' => 'JUR-00']))
        ->assertSee('These admission numbers contain it')
        ->assertSee('Ada Obi')->assertSee('Chidi Eze');

    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['admission' => 'NOPE-1']))
        ->assertSee('No student has the admission number NOPE-1.');
});

test('the print page is a preview first, and prints at once only when asked', function () {
    $records = $this->jss1->pluck('uuid')->all();

    $this->actingAs($this->admin)
        ->post(route('id-cards.print'), ['type' => 'student', 'records' => $records])
        ->assertOk()
        ->assertSee('Preview: 3 ID cards')
        ->assertDontSee('window.print();', false);

    $this->actingAs($this->admin)
        ->post(route('id-cards.print'), ['type' => 'student', 'records' => [$records[0]], 'autoprint' => 1])
        ->assertOk()
        ->assertSee('window.print();', false);
});

test('a bulk PDF of the class downloads', function () {
    $this->actingAs($this->admin)
        ->post(route('id-cards.pdf'), ['type' => 'student', 'records' => $this->jss1->pluck('uuid')->all()])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('another school\'s students cannot be given cards from here', function () {
    $other = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->post(route('id-cards.generate'), ['type' => 'student', 'records' => [$other->uuid]])
        ->assertNotFound();

    expect(IssuedIdCard::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Staff
// ---------------------------------------------------------------------------

test('a staff member is found by Staff ID and the right list opens', function () {
    $porter = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::SupportStaff, 'first_name' => 'Musa', 'last_name' => 'Bala', 'staff_number' => 'GLA-STAFF-009']);
    Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'first_name' => 'Grace', 'last_name' => 'Ade']);

    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['tab' => 'teaching', 'staff_id' => 'gla-staff-009']))
        ->assertOk()
        ->assertSee('Musa Bala')
        ->assertSee('Staff ID GLA-STAFF-009 · Non-teaching staff')
        ->assertDontSee('Grace Ade');

    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['tab' => 'teaching', 'staff_id' => 'NOBODY']))
        ->assertSee('No staff member has the Staff ID NOBODY.');
});

test('staff cards are generated individually and in bulk', function () {
    $teachers = Staff::factory()->count(3)->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);

    $this->actingAs($this->admin)->post(route('id-cards.generate'), ['type' => 'teaching_staff', 'records' => [$teachers[0]->uuid]]);
    expect(idCardsFor($this->school))->toHaveCount(1);

    $this->actingAs($this->admin)->post(route('id-cards.generate'), ['type' => 'teaching_staff', 'records' => $teachers->pluck('uuid')->all()]);
    expect(idCardsFor($this->school))->toHaveCount(3)
        ->and(idCardsFor($this->school)->every(fn ($card) => $card->holder_type === IdCardHolderType::TeachingStaff))->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('id-cards.print'), ['type' => 'teaching_staff', 'records' => $teachers->pluck('uuid')->all()])
        ->assertOk()
        ->assertSee('Preview: 3 ID cards');
});

test('a staff member who moves from teaching to non-teaching keeps their one card', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);

    $this->actingAs($this->admin)->post(route('id-cards.generate'), ['type' => 'teaching_staff', 'records' => [$member->uuid]]);
    $number = IssuedIdCard::sole()->card_number;

    $member->update(['role' => StaffRole::SupportStaff]);
    $this->actingAs($this->admin)->post(route('id-cards.generate'), ['type' => 'non_teaching_staff', 'records' => [$member->uuid]]);

    $card = IssuedIdCard::sole();
    expect($card->card_number)->toBe($number)
        ->and($card->holder_type)->toBe(IdCardHolderType::NonTeachingStaff);
});

// ---------------------------------------------------------------------------
// Unique, permanent identification
// ---------------------------------------------------------------------------

test('the database refuses two students with one admission number at a school', function () {
    expect(fn () => Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'GLA-JUR-001']))
        ->toThrow(UniqueConstraintViolationException::class);

    // Another school may use the same number.
    expect(Student::factory()->create(['school_id' => School::factory()->create()->id, 'admission_number' => 'GLA-JUR-001'])->exists)->toBeTrue();
});

test('the database refuses two staff with one Staff ID at a school', function () {
    Staff::factory()->create(['school_id' => $this->school->id, 'staff_number' => 'GLA-STAFF-001']);

    expect(fn () => Staff::factory()->create(['school_id' => $this->school->id, 'staff_number' => 'GLA-STAFF-001']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the database refuses a second ID card record for the same person', function () {
    IssuedIdCard::issueFor($this->school, IdCardHolderType::Student, $this->jss1[0], null, $this->admin);

    expect(fn () => DB::table('issued_id_cards')->insert([
        'uuid' => (string) Str::uuid(), 'school_id' => $this->school->id, 'holder_type' => 'teaching_staff',
        'holder_uuid' => $this->jss1[0]->uuid, 'card_number' => 'X-1', 'serial_number' => 1, 'status' => 'active', 'issued_by' => $this->admin->id, 'issued_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('changing a student\'s class never changes their admission number', function (bool $auto) {
    $this->school->update(['auto_generate_admission_numbers' => $auto]);
    $student = $this->jss1[0];

    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'first_name' => $student->first_name, 'last_name' => $student->last_name,
            'gender' => 'female', 'class_name' => 'JSS 2', 'admission_number' => 'GLA-JUR-001',
        ])
        ->assertSessionHasNoErrors();

    expect($student->fresh()->class_name)->toBe('JSS 2')
        ->and($student->fresh()->admission_number)->toBe('GLA-JUR-001');

    // Nor can any code change it by the way.
    $student->fresh()->update(['class_name' => 'JSS 3', 'admission_number' => 'SOMETHING-ELSE']);
    expect($student->fresh()->admission_number)->toBe('GLA-JUR-001');
})->with(['auto-numbering on' => true, 'numbering by hand' => false]);

test('a School Admin numbering by hand can correct an admission number, and it is recorded', function () {
    $this->school->update(['auto_generate_admission_numbers' => false]);
    $student = $this->jss1[0];

    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'first_name' => $student->first_name, 'last_name' => $student->last_name,
            'gender' => 'female', 'class_name' => 'JSS 1', 'admission_number' => 'GLA-JUR-101',
        ])
        ->assertSessionHasNoErrors();

    expect($student->fresh()->admission_number)->toBe('GLA-JUR-101')
        ->and(IdentifierChange::where('holder_id', $student->id)->sole()->old_value)->toBe('GLA-JUR-001')
        ->and(AuditLog::where('action', 'student.admission-number-corrected')->exists())->toBeTrue();

    // A number someone else holds is still refused.
    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'first_name' => $student->first_name, 'last_name' => $student->last_name,
            'gender' => 'female', 'class_name' => 'JSS 1', 'admission_number' => 'GLA-JUR-002',
        ])
        ->assertSessionHasErrors('admission_number');
});

test('editing a staff member\'s details never changes their Staff ID', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'staff_number' => 'GLA-STAFF-004']);

    $member->update(['first_name' => 'Renamed', 'department' => 'Sciences', 'role' => StaffRole::SupportStaff, 'staff_number' => 'CHANGED']);

    expect($member->fresh()->staff_number)->toBe('GLA-STAFF-004')
        ->and($member->fresh()->first_name)->toBe('Renamed');
});

test('an approved request for a number someone else holds is refused, nothing changing', function () {
    $request = ProfileChangeRequest::factory()->create([
        'school_id' => $this->school->id, 'subject_type' => 'student', 'subject_uuid' => $this->jss1[0]->uuid,
        'field_key' => 'admission_number', 'field_label' => 'Admission Number',
        'requested_value' => 'gla-jur-002', 'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->post(route('profile-change-requests.approve', $request))
        ->assertSessionHasErrors('review_note');

    expect($this->jss1[0]->fresh()->admission_number)->toBe('GLA-JUR-001')
        ->and($request->fresh()->status)->toBe('pending');
});

// ---------------------------------------------------------------------------
// Cards stay linked to real records
// ---------------------------------------------------------------------------

test('deleting a student revokes their card; a card generated again is reissued with a new number and QR code', function () {
    $student = $this->jss1[0];
    $card = IssuedIdCard::issueFor($this->school, IdCardHolderType::Student, $student, null, $this->admin);
    $oldNumber = $card->card_number;
    $oldUuid = $card->uuid;

    $card->update(['status' => IssuedIdCardStatus::Revoked, 'revoked_at' => now()]);

    $this->actingAs($this->admin)
        ->post(route('id-cards.generate'), ['type' => 'student', 'records' => [$student->uuid]])
        ->assertSessionHas('status', fn ($status) => str_contains($status, '1 reissued with a new card number'));

    $reissued = IssuedIdCard::sole();
    expect($reissued->status)->toBe(IssuedIdCardStatus::Active)
        ->and($reissued->card_number)->not->toBe($oldNumber)
        ->and($reissued->uuid)->not->toBe($oldUuid);

    $student->delete();
    expect(IssuedIdCard::sole()->status)->toBe(IssuedIdCardStatus::Revoked);
});

test('a person with two cards from before is left with one by the migration', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id]);

    Schema::table('issued_id_cards', fn ($table) => $table->dropUnique('issued_id_cards_one_per_holder'));

    foreach ([['teaching_staff', 'revoked', 'A-1'], ['non_teaching_staff', 'active', 'A-2']] as [$type, $status, $number]) {
        DB::table('issued_id_cards')->insert([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->school->id, 'holder_type' => $type,
            'holder_uuid' => $member->uuid, 'card_number' => $number, 'serial_number' => 1, 'status' => $status, 'issued_by' => $this->admin->id,
            'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    (require database_path('migrations/2026_11_24_090000_one_id_card_per_person.php'))->up();

    expect(IssuedIdCard::where('holder_uuid', $member->uuid)->pluck('card_number')->all())->toBe(['A-2']);
});

// ---------------------------------------------------------------------------
// A school with no card template yet
// ---------------------------------------------------------------------------

test('with no template yet, choosing a class still lists every student, to tick all or some', function () {
    IdCardTemplate::where('school_id', $this->school->id)->delete();

    $this->actingAs($this->admin)
        ->get(route('id-cards.index', ['class' => 'JSS 1']))
        ->assertOk()
        ->assertSee('Ada Obi')->assertSee('Bola Ade')->assertSee('Chidi Eze')
        ->assertSee('name="records[]"', false)
        ->assertSee('Generate all')
        ->assertSee('Generate ticked')
        ->assertSee('Standard design (school colours)')
        ->assertDontSee('There is no card template');
});

test('with no template yet, cards are generated, previewed and printed in the standard design', function () {
    IdCardTemplate::where('school_id', $this->school->id)->delete();

    $this->actingAs($this->admin)
        ->post(route('id-cards.generate'), ['type' => 'student', 'records' => [$this->jss1[0]->uuid, $this->jss1[2]->uuid], 'template' => ''])
        ->assertSessionHas('status', fn ($status) => str_contains($status, '2 ID cards ready (2 new)'));

    expect(idCardsFor($this->school)->pluck('holder_uuid')->sort()->values()->all())
        ->toBe(collect([$this->jss1[0]->uuid, $this->jss1[2]->uuid])->sort()->values()->all());

    $this->actingAs($this->admin)
        ->post(route('id-cards.print'), ['type' => 'student', 'records' => [$this->jss1[0]->uuid, $this->jss1[2]->uuid], 'template' => ''])
        ->assertOk()
        ->assertSee('Preview: 2 ID cards')
        ->assertSee('GLA-JUR-001')->assertSee('GLA-JUR-003');

    $this->actingAs($this->admin)
        ->post(route('id-cards.pdf'), ['type' => 'student', 'records' => [$this->jss1[0]->uuid], 'template' => ''])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
