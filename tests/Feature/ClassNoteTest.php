<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Models\ClassNote;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Notifications\NewClassNotePosted;
use App\Support\PortalNavigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Class Note: one Word document, sent to every class a teacher ticks.
 *
 * The feature is a delivery rule, and the rule is the test:
 *
 *     school -> staff -> class note -> selected classes -> pupils in them
 *
 * Every link in that chain is a place a note could reach somebody it was never
 * meant for, so each one is held here, and the two that matter most are the
 * last: a pupil sees their own school's notes, and only the ones sent to the
 * class they are actually in.
 */
beforeEach(function () {
    Storage::fake('local');
});

/**
 * A school with classes set up and a member of staff in it.
 *
 * @param  list<string>  $classNames
 */
function noteSchool(PlanKey $plan = PlanKey::Standard, array $classNames = ['JSS 1A', 'JSS 1B', 'JSS 2A']): School
{
    $school = activateSchool(School::factory()->create(), $plan);

    foreach ($classNames as $index => $className) {
        SchoolClass::factory()->create([
            'school_id' => $school->id,
            'name' => $className,
            'sort_order' => $index,
        ]);
    }

    return $school->fresh();
}

function noteStaff(School $school, StaffRole $role = StaffRole::Teacher): Staff
{
    return Staff::factory()->create([
        'school_id' => $school->id,
        'role' => $role,
        'is_active' => true,
    ]);
}

function notePupil(School $school, string $className): Student
{
    return Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => $className,
        'is_active' => true,
    ]);
}

/**
 * A REAL .docx, built with PHPWord rather than faked.
 *
 * UploadedFile::fake() produces a file whose contents are not a Word document
 * at all, which the `mimetypes` rule would reject and the text extractor could
 * not read, so a fake would prove the opposite of what these tests are for.
 */
function wordDocument(string $name = 'note.docx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'class_note_').'.docx';

    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Photosynthesis is how a plant makes food.');
    $section->addText('Read pages 40 to 44 before Friday.');

    IOFactory::createWriter($word, 'Word2007')->save($path);

    return new UploadedFile(
        $path,
        $name,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );
}

describe('the staff side', function () {
    test('every member of staff has Class Note, on every plan', function (PlanKey $plan) {
        $school = noteSchool($plan);

        $this->actingAs(noteStaff($school), 'staff')
            ->get(route('staff.class-notes.index', $school))
            ->assertOk()
            ->assertSee('Class Note');
    })->with([
        'Basic' => PlanKey::Basic,
        'Standard' => PlanKey::Standard,
        'Exclusive' => PlanKey::Exclusive,
    ]);

    test('it is in the sidebar for a non-teaching member too', function () {
        $school = noteSchool(PlanKey::Basic);

        // "All registered staff", the bursar and the librarian included.
        $nav = PortalNavigation::forStaff(noteStaff($school, StaffRole::SupportStaff));

        expect(collect($nav['categories']['Academic'])->pluck('route'))
            ->toContain('staff.class-notes.index');
    });

    test('a note goes to every ticked class at once', function () {
        Notification::fake();

        $school = noteSchool();
        $staff = noteStaff($school);

        $inA = notePupil($school, 'JSS 1A');
        $inB = notePupil($school, 'JSS 1B');
        $elsewhere = notePupil($school, 'JSS 2A');

        $this->actingAs($staff, 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Photosynthesis',
                'subject' => 'Basic Science',
                'class_names' => ['JSS 1A', 'JSS 1B'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $note = ClassNote::sole();

        // ONE row and ONE file, two classes, not a copy per class.
        expect($note->classNames()->sort()->values()->all())->toBe(['JSS 1A', 'JSS 1B'])
            ->and(ClassNote::count())->toBe(1);

        Storage::disk('local')->assertExists($note->path);

        Notification::assertSentTo($inA, NewClassNotePosted::class);
        Notification::assertSentTo($inB, NewClassNotePosted::class);
        Notification::assertNotSentTo($elsewhere, NewClassNotePosted::class);
    });

    test('the document is stored privately, never on the public disk', function () {
        Storage::fake('public');

        $school = noteSchool();

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Algebra',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors();

        $note = ClassNote::sole();

        Storage::disk('local')->assertExists($note->path);
        Storage::disk('public')->assertMissing($note->path);
    });

    test('its text is read out of the document so a pupil can copy it', function () {
        $school = noteSchool();

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Algebra',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors();

        expect(ClassNote::sole()->isReadable())->toBeTrue();
    });

    test('anything that is not a Word document is refused', function (string $name, string $mime) {
        $school = noteSchool();

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Not a note',
                'class_names' => ['JSS 1A'],
                'document' => UploadedFile::fake()->create($name, 40, $mime),
            ])
            ->assertSessionHasErrors('document');

        expect(ClassNote::count())->toBe(0);
    })->with([
        'a PDF' => ['notes.pdf', 'application/pdf'],
        'an image' => ['notes.png', 'image/png'],
        'a spreadsheet' => ['notes.xlsx', 'application/vnd.ms-excel'],
        'a script renamed to .docx' => ['notes.docx', 'application/x-php'],
    ]);

    test('a note with no class chosen is refused', function () {
        $school = noteSchool();

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Nowhere',
                'document' => wordDocument(),
            ])
            ->assertSessionHasErrors('class_names');

        expect(ClassNote::count())->toBe(0);
    });

    test('an oversized document is refused', function () {
        $school = noteSchool();

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Huge',
                'class_names' => ['JSS 1A'],
                'document' => UploadedFile::fake()->create('huge.docx', 11 * 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertSessionHasErrors('document');
    });

    test('a teacher sees only their own notes listed', function () {
        $school = noteSchool();
        $mine = noteStaff($school);
        $theirs = noteStaff($school);

        ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id, 'staff_id' => $theirs->id, 'title' => 'Their note']);
        ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id, 'staff_id' => $mine->id, 'title' => 'My note']);

        $this->actingAs($mine, 'staff')
            ->get(route('staff.class-notes.index', $school))
            ->assertOk()
            ->assertSee('My note')
            ->assertDontSee('Their note');
    });

    test('only the teacher who sent a note may withdraw it', function () {
        $school = noteSchool();
        $author = noteStaff($school);
        $other = noteStaff($school);

        $note = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id, 'staff_id' => $author->id]);

        $this->actingAs($other, 'staff')
            ->delete(route('staff.class-notes.destroy', [$school, $note]))
            ->assertForbidden();

        expect(ClassNote::whereKey($note->id)->exists())->toBeTrue();
    });
});

describe('a class a teacher may not name', function () {
    test('a class this school does not have is refused', function () {
        $school = noteSchool();

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Invented',
                'class_names' => ['JSS 1A', 'A Class That Does Not Exist'],
                'document' => wordDocument(),
            ])
            // The invented one, at its own index, the valid class beside it
            // does not launder it through.
            ->assertSessionHasErrors('class_names.1');

        expect(ClassNote::count())->toBe(0);
    });

    test("ANOTHER SCHOOL'S CLASS IS REFUSED", function () {
        $mine = noteSchool(classNames: ['JSS 1A']);
        $theirs = noteSchool(classNames: ['Riverside Form 1']);

        $theirPupil = notePupil($theirs, 'Riverside Form 1');

        // The core isolation rule. A teacher at one school naming a class at
        // another must not reach that school's pupils.
        $this->actingAs(noteStaff($mine), 'staff')
            ->post(route('staff.class-notes.store', $mine), [
                'title' => 'Reaching across',
                'class_names' => ['Riverside Form 1'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasErrors('class_names.0');

        expect(ClassNote::count())->toBe(0)
            ->and($theirPupil->notifications()->count())->toBe(0);
    });

    test('a note cannot be posted into another school at all', function () {
        $mine = noteSchool();
        $theirs = noteSchool();

        $this->actingAs(noteStaff($mine), 'staff')
            ->post(route('staff.class-notes.store', $theirs), [
                'title' => 'Wrong school',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            // 404 rather than 403, and from EnsureStaffIsActive rather than
            // from this feature: the staff portal already refuses a request
            // whose address names a school the signed-in member is not in.
            // The controller checks it again anyway, two locks on a door
            // that only needs one is the right number for this one.
            ->assertNotFound();

        expect(ClassNote::count())->toBe(0);
    });

    test("a teacher cannot download another school's note", function () {
        $mine = noteSchool();
        $theirs = noteSchool();

        $note = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $theirs->id]);

        $this->actingAs(noteStaff($mine), 'staff')
            ->get(route('staff.class-notes.download', [$mine, $note]))
            ->assertForbidden();
    });
});

describe('the student side', function () {
    test('a pupil sees the notes sent to their class', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id, 'title' => 'For my class']);

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.index', $school))
            ->assertOk()
            ->assertSee('For my class');
    });

    test('A PUPIL NEVER SEES ANOTHER CLASS NOTE', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        ClassNote::factory()->sentTo(['JSS 2A'])->create(['school_id' => $school->id, 'title' => 'Not for them']);

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.index', $school))
            ->assertOk()
            ->assertDontSee('Not for them');
    });

    test('opening another class note by its uuid is a 404', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        $note = ClassNote::factory()->sentTo(['JSS 2A'])->create(['school_id' => $school->id]);

        // Not hidden, unreachable. The address is guessable; the answer is not.
        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.show', [$school, $note]))
            ->assertNotFound();

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.download', [$school, $note]))
            ->assertNotFound();
    });

    test("ANOTHER SCHOOL'S NOTE IS UNREACHABLE, even for the same class name", function () {
        $mine = noteSchool(classNames: ['JSS 1A']);
        $theirs = noteSchool(classNames: ['JSS 1A']);

        $pupil = notePupil($mine, 'JSS 1A');

        // Two schools with a class of the same name is the ordinary case, and
        // it is exactly what a class-only filter would get wrong.
        $note = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $theirs->id, 'title' => 'Other school']);

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.index', $mine))
            ->assertOk()
            ->assertDontSee('Other school');

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.show', [$mine, $note]))
            ->assertNotFound();
    });

    test('a pupil with no class recorded receives nothing, rather than everything', function () {
        $school = noteSchool();
        $pupil = Student::factory()->create(['school_id' => $school->id, 'class_name' => null, 'is_active' => true]);

        ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id, 'title' => 'Somebody else']);

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.index', $school))
            ->assertOk()
            ->assertDontSee('Somebody else');
    });

    test('the note can be read and copied in the portal', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        $note = ClassNote::factory()->sentTo(['JSS 1A'])->create([
            'school_id' => $school->id,
            'body_text' => 'Photosynthesis is how a plant makes food.',
        ]);

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.show', [$school, $note]))
            ->assertOk()
            ->assertSee('Photosynthesis is how a plant makes food.')
            ->assertSee('Copy note');
    });

    test('a legacy .doc says so instead of showing an empty panel', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        $note = ClassNote::factory()->downloadOnly()->sentTo(['JSS 1A'])->create(['school_id' => $school->id]);

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.show', [$school, $note]))
            ->assertOk()
            ->assertSee('This note can only be downloaded');
    });

    test('the pupil can download the document itself', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        $note = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id]);
        Storage::disk('local')->put($note->path, 'the document');

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.download', [$school, $note]))
            ->assertOk()
            ->assertDownload($note->original_name);
    });
});

describe('notifications', function () {
    test('the notification points straight at the note', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Photosynthesis',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors();

        $note = ClassNote::sole();
        $data = $pupil->notifications()->sole()->data;

        expect($data['title'])->toContain('Photosynthesis')
            ->and($data['body'])->toContain('JSS 1A')
            ->and($data['url'])->toBe(route('student.class-notes.show', [$school, $note]));
    });

    test('the notifications list links to it', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');
        $note = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id]);

        $pupil->notify(new NewClassNotePosted($note, 'JSS 1A'));

        $this->actingAs($pupil, 'student')
            ->get(route('student.notifications.index', $school))
            ->assertOk()
            ->assertSee(route('student.class-notes.show', [$school, $note]), false);
    });

    test('a pupil in a class that was not chosen is not notified', function () {
        Notification::fake();

        $school = noteSchool();
        $chosen = notePupil($school, 'JSS 1A');
        $notChosen = notePupil($school, 'JSS 2A');

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Only 1A',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($chosen, NewClassNotePosted::class);
        Notification::assertNotSentTo($notChosen, NewClassNotePosted::class);
    });

    test('an inactive pupil is not notified', function () {
        Notification::fake();

        $school = noteSchool();
        $left = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1A', 'is_active' => false]);

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Term work',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors();

        Notification::assertNotSentTo($left, NewClassNotePosted::class);
    });

    test('opening the note clears its own notification and no other', function () {
        $school = noteSchool();
        $pupil = notePupil($school, 'JSS 1A');

        $mine = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id]);
        $other = ClassNote::factory()->sentTo(['JSS 1A'])->create(['school_id' => $school->id]);

        $pupil->notify(new NewClassNotePosted($mine, 'JSS 1A'));
        $pupil->notify(new NewClassNotePosted($other, 'JSS 1A'));

        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.show', [$school, $mine]))
            ->assertOk();

        expect($pupil->unreadNotifications()->count())->toBe(1);
    });
});

describe('the Basic plan keeps its shape', function () {
    test('a Basic school has no student Class Note page, because it has no student portal', function () {
        $school = noteSchool(PlanKey::Basic);
        $pupil = notePupil($school, 'JSS 1A');

        // The pupil's whole portal is behind portal_access, which Basic fails.
        // This feature does not open a door that plan keeps shut.
        $this->actingAs($pupil, 'student')
            ->get(route('student.class-notes.index', $school))
            ->assertRedirect(route('student.locked', $school));
    });

    test('a Basic school still has the staff side', function () {
        $school = noteSchool(PlanKey::Basic);

        $this->actingAs(noteStaff($school), 'staff')
            ->post(route('staff.class-notes.store', $school), [
                'title' => 'Kept for later',
                'class_names' => ['JSS 1A'],
                'document' => wordDocument(),
            ])
            ->assertSessionHasNoErrors();

        expect(ClassNote::count())->toBe(1);
    });

    test('the staff page says there is no student portal to receive it', function () {
        $school = noteSchool(PlanKey::Basic);

        $this->actingAs(noteStaff($school), 'staff')
            ->get(route('staff.class-notes.index', $school))
            ->assertOk()
            ->assertSee('Your plan has no student portal');
    });

    test('a Standard school says no such thing', function () {
        $school = noteSchool(PlanKey::Standard);

        $this->actingAs(noteStaff($school), 'staff')
            ->get(route('staff.class-notes.index', $school))
            ->assertOk()
            ->assertDontSee('Your plan has no student portal');
    });

    test('the parent portal gains nothing', function () {
        // Requirement 5 is explicit: no Class Note in the parent portal on any
        // plan. The surest way to keep a link off a page is never to build it.
        expect(collect(app('router')->getRoutes()->getRoutes())
            ->map->getName()
            ->filter()
            ->filter(fn (string $name) => str_starts_with($name, 'guardian.') && str_contains($name, 'class-note'))
        )->toBeEmpty();
    });
});
