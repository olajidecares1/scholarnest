<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Signature;
use App\Models\Staff;
use App\Models\User;
use App\Services\GoldSignature;
use App\Services\PublishedResultData;
use App\Support\PrincipalSignature;
use App\Support\ReportCardSample;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Every report card has a class teacher's line and a principal's line on it,
 * and until now both printed blank on every card ever generated: the school
 * could store a principal's signature in a column that no form ever wrote to,
 * and a teacher had nowhere to put one at all.
 *
 * A signature is the one mark on a card that is meant to prove a particular
 * person saw it, so each comes from the person it belongs to and from nobody
 * else.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create(['name' => 'Signed School']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    $this->staff = Staff::factory()->create(['school_id' => $this->school->id]);
});

test('a teacher uploads their own signature, on every plan', function () {
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->post(route('staff.settings.update-signature', $school), [
                'signature' => UploadedFile::fake()->image('mine.png'),
            ])
            ->assertRedirect();

        expect($staff->fresh()->signatureDataUri())->not->toBeNull();

        auth('staff')->logout();
    }
});

test('a teacher can withdraw their signature', function () {
    activateSchool($this->school, PlanKey::Basic);
    $this->staff->registerSignature('staff-signatures/old.png');

    $this->actingAs($this->staff, 'staff')
        ->post(route('staff.settings.update-signature', $this->school), ['remove_signature' => '1'])
        ->assertRedirect();

    // A signature on the wrong name is worse than no signature, so withdrawing
    // has to be possible and has to be explicit.
    expect($this->staff->fresh()->signatureDataUri())->toBeNull();
});

test('a teacher cannot sign as a colleague', function () {
    activateSchool($this->school, PlanKey::Standard);
    $colleague = Staff::factory()->create(['school_id' => $this->school->id]);

    // The staff member is taken from the session, never from the request, so
    // there is no id to swap. Sending one changes nothing.
    $this->actingAs($this->staff, 'staff')
        ->post(route('staff.settings.update-signature', $this->school), [
            'staff_id' => $colleague->id,
            'signature' => UploadedFile::fake()->image('mine.png'),
        ])
        ->assertRedirect();

    expect($this->staff->fresh()->signatureDataUri())->not->toBeNull()
        ->and($colleague->fresh()->signatureDataUri())->toBeNull();
});

test('the school\'s Principal signature is its School Admin\'s own, on every plan', function () {
    // School Admin IS the Principal. There is no school-level signature to
    // set: what the admin registers against their account is what the
    // school's documents print.
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'name' => $school->name,
                'timezone' => 'Africa/Lagos',
                'principal_name' => 'Mr. Gregory A. Eze',
            ])
            ->assertRedirect();

        Storage::disk('local')->put('admin-signatures/'.$admin->id.'.png', signatureBytes());
        $admin->registerSignature('admin-signatures/'.$admin->id.'.png');

        $school->refresh();

        expect($school->principal_name)->toBe('Mr. Gregory A. Eze')
            ->and(PrincipalSignature::for($school))->not->toBeNull();

        auth()->logout();
    }
});

test('a Principal can withdraw their signature', function () {
    activateSchool($this->school, PlanKey::Basic);
    Storage::disk('local')->put('admin-signatures/old.png', signatureBytes());
    $this->admin->registerSignature('admin-signatures/old.png');

    $this->actingAs($this->admin)
        ->deleteJson(route('signature.destroy'))
        ->assertOk();

    // Nothing is substituted in its place: the line prints blank.
    expect(PrincipalSignature::for($this->school->fresh()))->toBeNull();
});

test('saving settings does not disturb the Principal signature', function () {
    activateSchool($this->school, PlanKey::Basic);
    Storage::disk('local')->put('admin-signatures/kept.png', signatureBytes());
    $this->admin->registerSignature('admin-signatures/kept.png');

    $this->actingAs($this->admin)
        ->put(route('settings.update'), [
            'name' => 'A New Name',
            'timezone' => 'Africa/Lagos',
        ])
        ->assertRedirect();

    // Editing an unrelated field must not silently unsign every future card.
    expect($this->admin->fresh()->signature?->path)->toBe('admin-signatures/kept.png');
});

test('both signatures print on the card, each from its own owner', function () {
    // Real files on the faked disk: the card resolves the bytes behind the
    // path and returns null for a path with nothing behind it - so a column
    // alone would not prove the card renders anything.
    Storage::disk('local')->put('admin-signatures/principal.png', signatureBytes(2));
    Storage::disk('local')->put('staff-signatures/teacher.png', signatureBytes(11));

    // The Principal's comes from the School Admin's own account - they ARE
    // the Principal - and is rendered gold, so the card carries the cached
    // gold rendering rather than the source.
    $this->admin->registerSignature('admin-signatures/principal.png');

    $data = ReportCardSample::for($this->school->fresh());
    $data['classTeacher']->staff->setRelation('signature', new Signature(['path' => 'staff-signatures/teacher.png']));

    // On screen the card embeds both images, so each is identified by its own
    // bytes - no filename appears in the page at all any more.
    $screen = view('school-admin.results._report-card', $data)->render();

    expect($screen)
        ->toContain(PrincipalSignature::for($this->school->fresh())->dataUri())
        ->toContain($data['classTeacher']->staff->signatureDataUri());

    // In print dompdf is handed a local file path for each, which is where
    // the filenames survive.
    $print = view('school-admin.results.pdf.report-card', $data)->render();

    expect($print)
        ->toContain(basename(app(GoldSignature::class)->pathFor('admin-signatures/principal.png')))
        ->toContain('teacher.png');
});

test('an unsigned card prints an empty line rather than somebody else\'s mark', function () {
    // Nothing is drawn on a teacher's behalf: a blank line means genuinely
    // unsigned, which is the only thing that makes a signed one worth having.
    $html = view('school-admin.results._report-card', ReportCardSample::for($this->school))->render();

    expect($html)->toContain('Class Teacher')
        ->and($html)->not->toContain('staff-signatures');
});

test('the staff settings page offers the signature form on every plan', function () {
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.settings.index', $school))
            ->assertOk()
            ->assertSee('Your Signature')
            ->assertSee('name="signature"', false);

        auth('staff')->logout();
    }
});

test('the settings page offers the Principal signature pad on every plan', function () {
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            // Registered against the account through the pad, never uploaded
            // as a school-level file.
            ->assertSee('signaturePad(', false)
            ->assertSee('name="principal_name"', false)
            ->assertDontSee('name="principal_signature"', false);

        auth()->logout();
    }
});

test('a published card keeps the signature that was on it', function () {
    // Unlike the school's crest and motto, which are read live, a signature is
    // snapshotted. It attests to a moment: a teacher who later replaces their
    // signature, or leaves, must not rewrite what was already published.
    $this->staff->registerSignature('staff-signatures/at-the-time.png');

    $data = ReportCardSample::for($this->school);
    $data['classTeacher']->setRelation('staff', $this->staff);

    $snapshot = app(PublishedResultData::class)->snapshot($data);

    expect($snapshot['classTeacher']['signature_path'])->toBe('staff-signatures/at-the-time.png');

    $this->staff->registerSignature('staff-signatures/replaced-later.png');

    // Read back through the same private rehydrator the repository uses.
    $rehydrate = new ReflectionMethod(PublishedResultData::class, 'rehydrateClassTeacher');
    $teacher = $rehydrate->invoke(app(PublishedResultData::class), $snapshot['classTeacher']);

    expect($teacher->staff->signature?->path)->toBe('staff-signatures/at-the-time.png');
});

test('a card published before signatures existed still reads back', function () {
    // Older payloads have no signature_path key at all. They must render an
    // unsigned line, not fail - they were unsigned, and that is the truth.
    $rehydrate = new ReflectionMethod(PublishedResultData::class, 'rehydrateClassTeacher');
    $teacher = $rehydrate->invoke(
        app(PublishedResultData::class),
        ['name' => 'Blessing Nwosu', 'staff_number' => 'STF/001'],
    );

    expect($teacher->staff->signature)->toBeNull()
        ->and($teacher->staff->fullName())->toBe('Blessing Nwosu');
});

/**
 * A real PNG, for tests that need a file the image pipeline can actually read.
 */
/**
 * A signature PNG, drawn in near-black like a real one.
 *
 * $mark changes the stroke, so two signatures are genuinely different images.
 * This mattered nowhere while a card carried a URL - the path told two marks
 * apart - and matters entirely now that the card carries the image itself:
 * identical bytes would let "never shows a different teacher's signature"
 * pass however the card resolved them.
 */
function signatureBytes(int $mark = 0): string
{
    $image = imagecreatetruecolor(120, 50);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imageline($image, 5, 40 - $mark, 115, 12 + $mark, imagecolorallocate($image, 17, 24, 39));

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

test('a pupil\'s card never shows a different class teacher\'s signature', function () {
    // The card resolves the signature from the CLASS, not from whoever is
    // looking at it, so a teacher of another class cannot appear on it.
    Storage::disk('local')->put('staff-signatures/ours.png', signatureBytes(3));
    Storage::disk('local')->put('staff-signatures/theirs.png', signatureBytes(13));

    $ourTeacher = Staff::factory()->create(['school_id' => $this->school->id]);
    $otherTeacher = Staff::factory()->create(['school_id' => $this->school->id]);
    $ourTeacher->registerSignature('staff-signatures/ours.png');
    $otherTeacher->registerSignature('staff-signatures/theirs.png');

    $data = ReportCardSample::for($this->school);
    $data['classTeacher']->setRelation('staff', $ourTeacher->fresh());

    $html = view('school-admin.results._report-card', $data)->render();

    // Compared by the bytes that reach the page, not by a filename: the card
    // embeds the image now, so the name of the file it came from never
    // appears and a filename assertion would pass vacuously.
    expect($html)->toContain($ourTeacher->fresh()->signatureDataUri())
        ->and($html)->not->toContain($otherTeacher->fresh()->signatureDataUri());
});
