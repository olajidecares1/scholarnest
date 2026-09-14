<?php

use App\Enums\Gender;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A passport photograph, however it was taken.
 *
 * The camera and the file picker both end at the SAME file input, so a
 * captured photograph reaches the server as an ordinary upload, identical
 * validation, identical optimisation, identical private-disk storage. These
 * tests exercise that one path, because there is only one.
 *
 * The rule that matters most is the last one: a photograph belongs to the
 * record it was uploaded against, and to no other.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

describe('the form offers both ways of adding one', function () {
    test('the pupil form has a camera and an upload', function () {
        $this->actingAs($this->admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Take Photo')
            ->assertSee('Upload Photo')
            ->assertSee('photoField(', false)
            ->assertSee('id="student_photo"', false);
    });

    test('the staff form has the same one', function () {
        $this->actingAs($this->admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Take Photo')
            ->assertSee('Upload Photo')
            ->assertSee('photoField(', false)
            ->assertSee('id="staff_photo"', false);
    });

    test('so does the account profile page', function () {
        $this->actingAs($this->admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Take Photo')
            ->assertSee('photoField(', false)
            ->assertSee('id="account_photo"', false);
    });
});

describe('what arrives is stored privately and bound to its own record', function () {
    test('a pupil photograph lands on the private disk', function () {
        $this->actingAs($this->admin)
            ->post(route('students.store'), [
                'admission_number' => 'ADM-100',
                'first_name' => 'Chidi',
                'last_name' => 'Okeke',
                'gender' => Gender::Male->value,
                'class_name' => 'JSS 1',
                'photo' => UploadedFile::fake()->image('passport.jpg', 600, 600),
            ])
            ->assertSessionHasNoErrors();

        $student = Student::where('admission_number', 'ADM-100')->sole();

        expect($student->photo_path)->not->toBeNull();

        Storage::disk('local')->assertExists($student->photo_path);
        Storage::disk('public')->assertMissing($student->photo_path);
    });

    test('a staff photograph does too', function () {
        $this->actingAs($this->admin)
            ->post(route('staff.store'), [
                'first_name' => 'Bisi',
                'last_name' => 'Ade',
                'gender' => Gender::Female->value,
                'role' => StaffRole::Teacher->value,
                'photo' => UploadedFile::fake()->image('passport.png', 600, 600),
            ])
            ->assertSessionHasNoErrors();

        // Found by name: the Staff ID is issued by the system, not submitted.
        $member = Staff::where('last_name', 'Ade')->sole();

        expect($member->photo_path)->not->toBeNull();

        Storage::disk('local')->assertExists($member->photo_path);
    });

    test('a file that is not an image is refused', function () {
        $this->actingAs($this->admin)
            ->post(route('students.store'), [
                'admission_number' => 'ADM-101',
                'first_name' => 'Ada',
                'last_name' => 'Nwosu',
                'gender' => Gender::Female->value,
                'class_name' => 'JSS 1',
                'photo' => UploadedFile::fake()->create('notaphoto.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        expect(Student::where('admission_number', 'ADM-101')->exists())->toBeFalse();
    });

    test('an oversized photograph is refused', function () {
        $this->actingAs($this->admin)
            ->post(route('students.store'), [
                'admission_number' => 'ADM-102',
                'first_name' => 'Ada',
                'last_name' => 'Nwosu',
                'gender' => Gender::Female->value,
                'class_name' => 'JSS 1',
                'photo' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');
    });

    test('editing without choosing a photograph keeps the one already there', function () {
        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'photo_path' => 'students/existing.jpg',
        ]);

        $this->actingAs($this->admin)
            ->put(route('students.update', $student), [
                'admission_number' => $student->admission_number,
                'first_name' => 'Renamed',
                'last_name' => $student->last_name,
                'gender' => Gender::Male->value,
                'class_name' => $student->class_name,
            ])
            ->assertSessionHasNoErrors();

        // Editing a phone number must not silently remove somebody's face.
        expect($student->fresh()->photo_path)->toBe('students/existing.jpg')
            ->and($student->fresh()->first_name)->toBe('Renamed');
    });

    test('a photograph never lands on another pupil record', function () {
        $other = Student::factory()->create([
            'school_id' => $this->school->id,
            'photo_path' => 'students/theirs.jpg',
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.store'), [
                'admission_number' => 'ADM-103',
                'first_name' => 'Chidi',
                'last_name' => 'Okeke',
                'gender' => Gender::Male->value,
                'class_name' => 'JSS 1',
                'photo' => UploadedFile::fake()->image('mine.jpg', 600, 600),

                // Submitted on purpose. The photograph is written to the record
                // this request CREATES, and there is no student id in the form
                // for it to follow somewhere else.
                'student_id' => $other->id,
                'photo_path' => 'students/theirs.jpg',
            ])
            ->assertSessionHasNoErrors();

        $created = Student::where('admission_number', 'ADM-103')->sole();

        expect($created->photo_path)->not->toBe('students/theirs.jpg')
            ->and($other->fresh()->photo_path)->toBe('students/theirs.jpg')
            ->and($created->id)->not->toBe($other->id);
    });
});

/**
 * Everywhere a photograph is printed, it is read off the record being printed.
 *
 * The URL carries the holder's own uuid, so a page showing one person can never
 * address another person's file, there is no id in the markup to change.
 */
describe('the record decides whose face is shown', function () {
    test('a pupil page shows that pupil and nobody else', function () {
        $mine = Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'students/mine.jpg']);
        $theirs = Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'students/theirs.jpg']);

        $this->actingAs($this->admin)
            ->get(route('students.show', $mine))
            ->assertOk()
            ->assertSee("media/student/{$mine->uuid}", false)
            ->assertDontSee("media/student/{$theirs->uuid}", false);
    });

    test('a staff page shows that staff member and nobody else', function () {
        $mine = Staff::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'staff/mine.jpg']);
        $theirs = Staff::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'staff/theirs.jpg']);

        $this->actingAs($this->admin)
            ->get(route('staff.show', $mine))
            ->assertOk()
            ->assertSee("media/staff/{$mine->uuid}", false)
            ->assertDontSee("media/staff/{$theirs->uuid}", false);
    });

    test('the photograph URL is minted from the record, never from an id in a form', function () {
        $mine = Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'students/mine.jpg']);
        $theirs = Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'students/theirs.jpg']);

        expect($mine->photoUrl())->toContain($mine->uuid)
            ->and($mine->photoUrl())->not->toContain($theirs->uuid)
            ->and($mine->photoUrl())->not->toContain("/{$mine->id}/");
    });
});
