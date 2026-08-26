<?php

use App\Enums\DiaryEntryStatus;
use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\TeacherDiaryEntry;
use App\Models\User;
use App\Notifications\DiaryEntrySeen;
use App\Notifications\DiaryEntrySubmitted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * The teacher diary, and the loop it closes.
 *
 * A teacher logs the topic they taught each week, subject by subject; the
 * school reads it and says so. Both halves matter - a diary submitted into
 * silence gives a teacher no way to tell one that is being read from one that
 * is not, and they stop writing it carefully.
 */
function diarySchool(PlanKey $planKey = PlanKey::Standard): array
{
    $school = School::factory()->create(['current_session' => '2025/2026']);
    activateSchool($school, $planKey);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    foreach ([['JSS 1', 'Mathematics'], ['JSS 2', 'Further Mathematics']] as [$class, $subject]) {
        TeacherAssignment::create([
            'school_id' => $school->id,
            'staff_id' => $teacher->id,
            'type' => TeacherAssignmentType::SubjectTeacher->value,
            'class_name' => $class,
            'subject' => $subject,
        ]);
    }

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return [$school->fresh(), $teacher, $admin];
}

function submitDiaryEntry(Staff $teacher, School $school, array $overrides = []): TestResponse
{
    return test()->actingAs($teacher, 'staff')->post(route('staff.diary.store', $school), [
        'assignment' => 'JSS 1|Mathematics',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
        'week_number' => 3,
        'topic' => 'Quadratic equations — factorisation.',
        ...$overrides,
    ]);
}

// -----------------------------------------------------------------------------
// Standard and Exclusive only
// -----------------------------------------------------------------------------

test('the diary is available to teachers on standard and exclusive', function (PlanKey $planKey) {
    [$school, $teacher] = diarySchool($planKey);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.diary.index', $school))
        ->assertOk()
        ->assertSee('Mathematics');
})->with([
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('a basic school has no diary, on either side', function () {
    [$school, $teacher, $admin] = diarySchool(PlanKey::Basic);

    $this->actingAs($teacher, 'staff')->get(route('staff.diary.index', $school))->assertForbidden();
    $this->actingAs($teacher, 'staff')->post(route('staff.diary.store', $school), [])->assertForbidden();
    $this->actingAs($admin)->get(route('diary.index'))->assertForbidden();
});

test('a basic school is not offered the diary anywhere it could click it', function () {
    [$school, $teacher, $admin] = diarySchool(PlanKey::Basic);

    // A link that 403s is worse than no link.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('diary.index'));

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertDontSee(route('staff.diary.index', $school));
});

test('a standard school is offered it, with a folder icon', function () {
    [$school, $teacher, $admin] = diarySchool();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('diary.index'))
        ->assertSee('fa-folder', false);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertSee(route('staff.diary.index', $school))
        ->assertSee('fa-folder', false);
});

// -----------------------------------------------------------------------------
// Writing an entry
// -----------------------------------------------------------------------------

test('a teacher records a week\'s topic against a subject they teach', function () {
    Notification::fake();

    [$school, $teacher, $admin] = diarySchool();

    submitDiaryEntry($teacher, $school)->assertSessionHasNoErrors();

    $entry = TeacherDiaryEntry::firstOrFail();

    // Every dimension the brief asks the diary to organise by is a column, not
    // something to be reconstructed later.
    expect($entry->school_id)->toBe($school->id)
        ->and($entry->staff_id)->toBe($teacher->id)
        ->and($entry->class_name)->toBe('JSS 1')
        ->and($entry->subject)->toBe('Mathematics')
        ->and($entry->session)->toBe('2025/2026')
        ->and($entry->term)->toBe(ExamTerm::Second)
        ->and($entry->week_number)->toBe(3)
        ->and($entry->status)->toBe(DiaryEntryStatus::Submitted);

    // A diary nobody is told about is a diary nobody reads.
    Notification::assertSentTo($admin, DiaryEntrySubmitted::class);
});

test('a teacher cannot write against a subject or class they are not assigned to', function (string $assignment) {
    [$school, $teacher] = diarySchool();

    submitDiaryEntry($teacher, $school, ['assignment' => $assignment])
        ->assertSessionHasErrors('assignment');

    expect(TeacherDiaryEntry::count())->toBe(0);
})->with([
    'a subject they do not teach' => 'JSS 1|Chemistry',
    'a class they do not teach' => 'JSS 3|Mathematics',

    // The pair matters, not the two values separately: this teacher teaches
    // Mathematics, and teaches JSS 2 - but not Mathematics to JSS 2.
    'a real subject to the wrong class' => 'JSS 2|Mathematics',
]);

test('writing again for the same week revises that entry rather than adding another', function () {
    [$school, $teacher] = diarySchool();

    submitDiaryEntry($teacher, $school, ['topic' => 'First draft.']);
    submitDiaryEntry($teacher, $school, ['topic' => 'Corrected topic.'])->assertSessionHasNoErrors();

    expect(TeacherDiaryEntry::count())->toBe(1)
        ->and(TeacherDiaryEntry::first()->topic)->toBe('Corrected topic.');
});

test('a revised entry goes back for review', function () {
    [$school, $teacher, $admin] = diarySchool();

    submitDiaryEntry($teacher, $school);
    $entry = TeacherDiaryEntry::firstOrFail();

    $this->actingAs($admin)->post(route('diary.seen', $entry));
    expect($entry->fresh()->status)->toBe(DiaryEntryStatus::Seen);

    // The school approved what it read, not whatever replaced it.
    submitDiaryEntry($teacher, $school, ['topic' => 'Actually we covered something else.']);

    expect($entry->fresh()->status)->toBe(DiaryEntryStatus::Submitted)
        ->and($entry->fresh()->seen_by)->toBeNull()
        ->and($entry->fresh()->seen_at)->toBeNull();
});

test('separate weeks and subjects are separate entries', function () {
    [$school, $teacher] = diarySchool();

    submitDiaryEntry($teacher, $school, ['week_number' => 3]);
    submitDiaryEntry($teacher, $school, ['week_number' => 4]);
    submitDiaryEntry($teacher, $school, ['assignment' => 'JSS 2|Further Mathematics', 'week_number' => 3]);
    submitDiaryEntry($teacher, $school, ['week_number' => 3, 'term' => ExamTerm::Third->value]);

    expect(TeacherDiaryEntry::count())->toBe(4);
});

test('a teacher with no subject assignments is told why, not shown an empty box', function () {
    [$school, $teacher] = diarySchool();
    $teacher->assignments()->delete();

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.diary.index', $school))
        ->assertOk()
        ->assertSee('No subjects assigned to you yet')
        ->assertDontSee('Record this week');
});

// -----------------------------------------------------------------------------
// The school reads it, and says so
// -----------------------------------------------------------------------------

test('the school sees the entry with everything it needs to judge it', function () {
    [$school, $teacher, $admin] = diarySchool();
    submitDiaryEntry($teacher, $school);

    $this->actingAs($admin)
        ->get(route('diary.index'))
        ->assertOk()
        ->assertSee($teacher->fullName())
        ->assertSee('Mathematics')
        ->assertSee('JSS 1')
        ->assertSee('Week 3')
        ->assertSee('Second Term')
        ->assertSee('2025/2026')
        ->assertSee('Quadratic equations')
        ->assertSee('Mark as Seen');
});

test('marking an entry seen records who did it and tells the teacher', function () {
    Notification::fake();

    [$school, $teacher, $admin] = diarySchool();
    submitDiaryEntry($teacher, $school);
    $entry = TeacherDiaryEntry::firstOrFail();

    $this->actingAs($admin)->post(route('diary.seen', $entry))->assertRedirect();

    $entry->refresh();

    // "The school has seen this" is a claim the teacher is shown, and a claim
    // with nobody's name on it is worth less.
    expect($entry->status)->toBe(DiaryEntryStatus::Seen)
        ->and($entry->seen_by)->toBe($admin->id)
        ->and($entry->seen_at)->not->toBeNull();

    Notification::assertSentTo($teacher, DiaryEntrySeen::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'diary.entry.seen']);
});

test('the teacher can see whether each entry has been reviewed', function () {
    [$school, $teacher, $admin] = diarySchool();
    submitDiaryEntry($teacher, $school);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.diary.index', $school))
        ->assertOk()
        ->assertSee('Submitted');

    $this->actingAs($admin)->post(route('diary.seen', TeacherDiaryEntry::firstOrFail()));

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.diary.index', $school))
        ->assertOk()
        ->assertSee('Seen / Approved')
        ->assertSee($admin->name);
});

test('marking an entry seen twice does not re-notify the teacher', function () {
    [$school, $teacher, $admin] = diarySchool();
    submitDiaryEntry($teacher, $school);
    $entry = TeacherDiaryEntry::firstOrFail();

    $this->actingAs($admin)->post(route('diary.seen', $entry));

    Notification::fake();
    $this->actingAs($admin)->post(route('diary.seen', $entry))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'already'));

    Notification::assertNothingSent();
});

// -----------------------------------------------------------------------------
// One school's diary is its own
// -----------------------------------------------------------------------------

test('a school cannot see another school\'s diary entries', function () {
    [, , $admin] = diarySchool();
    [$otherSchool, $otherTeacher] = diarySchool();
    submitDiaryEntry($otherTeacher, $otherSchool, ['topic' => 'Their private topic.']);

    $this->actingAs($admin)
        ->get(route('diary.index'))
        ->assertOk()
        ->assertDontSee('Their private topic.')
        ->assertDontSee($otherTeacher->fullName());
});

test('a school cannot mark another school\'s entry as seen', function () {
    [, , $admin] = diarySchool();
    [$otherSchool, $otherTeacher] = diarySchool();
    submitDiaryEntry($otherTeacher, $otherSchool);
    $theirEntry = TeacherDiaryEntry::firstOrFail();

    // Bound by uuid and re-checked against this school, so the address cannot
    // be walked from one school's diary into another's.
    $this->actingAs($admin)->post(route('diary.seen', $theirEntry))->assertNotFound();

    expect($theirEntry->fresh()->status)->toBe(DiaryEntryStatus::Submitted);
});

test('a teacher only ever sees their own entries', function () {
    [$school, $teacher] = diarySchool();
    submitDiaryEntry($teacher, $school, ['topic' => 'My own topic.']);

    $colleague = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $colleague->id,
        'type' => TeacherAssignmentType::SubjectTeacher->value,
        'class_name' => 'JSS 1',
        'subject' => 'English Language',
    ]);

    $this->actingAs($colleague, 'staff')
        ->get(route('staff.diary.index', $school))
        ->assertOk()
        ->assertDontSee('My own topic.');
});
