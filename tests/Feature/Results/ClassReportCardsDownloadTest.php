<?php

use App\Enums\ExamTerm;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassReportCards;
use App\Services\ClassReportCardsFailed;
use App\Services\DefaultAcademicStructure;
use App\Support\PdfImage;
use Illuminate\Support\Facades\Storage;

/**
 * The School Admin downloading a whole class's report cards, or the ticked
 * pupils', as one PDF with a card to a page.
 */
function classDownloadFixture(int $pupils, int $subjects = 9): array
{
    $school = School::factory()->create(['name' => 'Triple Glory Academy']);
    DefaultAcademicStructure::seedFor($school);
    activateSchool($school);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 3', 'session' => '2026/2027', 'term' => ExamTerm::First]);
    $examination->subjects()->delete();

    $subjectRows = collect(range(1, $subjects))->map(fn ($i) => ExaminationSubject::create(['examination_id' => $examination->id, 'name' => "Subject {$i}", 'max_score' => 100]));

    $students = collect(range(1, $pupils))->map(function ($i) use ($school, $subjectRows) {
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 3', 'is_active' => true, 'admission_number' => sprintf('TGA-2026/2027-JUR-%03d', $i)]);

        foreach ($subjectRows as $subject) {
            ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $student->id, 'test_score' => 30, 'exam_score' => 40 + ($i % 20), 'score' => 70 + ($i % 20)]);
        }

        return $student;
    });

    return [$school, $admin, $examination, $students];
}

function pagesIn(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
}

test('the School Admin downloads the whole class as one PDF, a card to a page', function () {
    [, $admin, $examination, $students] = classDownloadFixture(5);

    $response = $this->actingAs($admin)->get(route('results.class-pdf', $examination))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('report-cards-JSS-3-First-Term-2026-2027.pdf')
        ->and(pagesIn($response->getContent()))->toBe(5);
});

test('only the ticked pupils, when some are ticked', function () {
    [, $admin, $examination, $students] = classDownloadFixture(5);

    $response = $this->actingAs($admin)
        ->get(route('results.class-pdf', ['examination' => $examination, 'students' => [$students[0]->uuid, $students[3]->uuid]]))
        ->assertOk();

    expect(pagesIn($response->getContent()))->toBe(2);
});

test('a class with many subjects still gives one page per pupil', function () {
    [, $admin, $examination] = classDownloadFixture(3, 22);

    expect(pagesIn($this->actingAs($admin)->get(route('results.class-pdf', $examination))->assertOk()->getContent()))->toBe(3);
});

test('the Results page offers the class download', function () {
    [, $admin, $examination] = classDownloadFixture(2);

    $this->actingAs($admin)
        ->get(route('results.index', ['class' => 'JSS 3', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()
        ->assertSee(route('results.class-pdf', $examination), false)
        ->assertSee('Download JSS 3 results');
});

test('another school\'s class cannot be downloaded', function () {
    [, , $examination] = classDownloadFixture(2);
    [, $otherAdmin] = classDownloadFixture(1);

    $this->actingAs($otherAdmin)->get(route('results.class-pdf', $examination))->assertForbidden();
});

test('a class with no results yet has nothing to download', function () {
    [$school, $admin] = classDownloadFixture(1);
    $empty = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 2', 'session' => '2026/2027', 'term' => ExamTerm::First]);
    $empty->subjects()->delete();

    $this->actingAs($admin)->get(route('results.class-pdf', $empty))->assertNotFound();
});

test('passport photos are shrunk before they go into a PDF', function () {
    [, $admin, $examination, $students] = classDownloadFixture(3);

    // A phone photo: 3000 x 2250 of noise, which compresses badly, as a
    // real photograph does.
    $image = imagecreatetruecolor(3000, 2250);
    for ($i = 0; $i < 60000; $i++) {
        imagesetpixel($image, random_int(0, 2999), random_int(0, 2249), random_int(0, 0xFFFFFF));
    }
    ob_start();
    imagejpeg($image, null, 95);
    $photo = (string) ob_get_clean();

    foreach ($students as $i => $student) {
        Storage::disk(Student::PHOTO_DISK)->put("photos/big-{$i}.jpg", $photo);
        $student->forceFill(['photo_path' => "photos/big-{$i}.jpg"])->save();
    }

    $pdf = $this->actingAs($admin)->get(route('results.class-pdf', $examination))->assertOk()->getContent();

    // Three photos at full size would be several megabytes on their own.
    expect(strlen($pdf))->toBeLessThan(strlen($photo))
        ->and(pagesIn($pdf))->toBe(3);
});

test('the Results page has a Select all button that works on a phone', function () {
    [, $admin] = classDownloadFixture(3);

    $this->actingAs($admin)
        ->get(route('results.index', ['class' => 'JSS 3', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()
        ->assertSee('Select all (3)')
        ->assertSee('picked = allPicked ? [] : [...all]', false);
});

test('when the class cannot be laid out as one document, every card still comes, in a zip', function () {
    [, $admin, $examination] = classDownloadFixture(3);

    // Laying the whole class out as one PDF fails.
    $this->mock(ClassReportCards::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('pdf')->andThrow(new RuntimeException('Frame not found in cellmap'));
    });

    $response = $this->actingAs($admin)->get(route('results.class-pdf', $examination))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/zip')
        ->and($response->headers->get('content-disposition'))->toContain('report-cards-JSS-3-First-Term-2026-2027.zip');

    $path = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($path, $response->getContent());
    $zip = new ZipArchive;
    $zip->open($path);

    expect($zip->numFiles)->toBe(3)
        ->and($zip->getNameIndex(0))->toEndWith('.pdf');

    $zip->close();
    unlink($path);
});

test('a card that cannot be made is named on the page, with the reason', function () {
    [, $admin, $examination, $students] = classDownloadFixture(2);

    $this->mock(ClassReportCards::class, function ($mock) use ($students) {
        $mock->makePartial();
        $mock->shouldReceive('download')->andThrow(new ClassReportCardsFailed([
            ['name' => $students[0]->fullName(), 'reason' => 'Image type unknown'],
        ]));
    });

    $this->actingAs($admin)
        ->from(route('results.index'))
        ->get(route('results.class-pdf', $examination))
        ->assertRedirect(route('results.index'))
        ->assertSessionHasErrors(['download' => 'These report cards could not be made: '.$students[0]->fullName().' (Image type unknown). Untick them to download the rest, and open one on its own to see it.']);
});

test('pictures for a PDF are kept in a real folder, never on a storage disk', function () {
    $image = imagecreatetruecolor(1200, 900);
    $source = tempnam(sys_get_temp_dir(), 'pic').'.jpg';
    imagejpeg($image, $source);

    $fitted = PdfImage::fit($source, 400);

    expect($fitted)->toStartWith(storage_path('framework/cache/pdf-images'))
        ->and(is_file($fitted))->toBeTrue()
        ->and(getimagesize($fitted)[0])->toBe(400);
});

test('no see-through picture reaches the PDF, so the live server never needs ImageMagick', function () {
    // On Laravel Cloud dompdf sends any PNG with an alpha channel to
    // ImageMagick, which refuses it: every card failed with "unable to open
    // image". A picture embedded with transparency carries an /SMask; a card
    // made only of solid pictures has none.
    [$school, $admin, $examination, $students] = classDownloadFixture(2);

    $png = function () {
        $image = imagecreatetruecolor(400, 300);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, 200, 150, 220, 160, imagecolorallocatealpha($image, 180, 30, 40, 20));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    };

    Storage::disk('public')->put('logos/crest.png', $png());
    Storage::disk('public')->put('stamps/stamp.png', $png());
    $school->forceFill(['logo_path' => 'logos/crest.png', 'stamp_path' => 'stamps/stamp.png'])->save();
    Storage::disk('local')->put('signatures/admin.png', $png());
    $admin->registerSignature('signatures/admin.png');

    $single = $this->actingAs($admin)->get(route('results.pdf', [$examination, $students[0]]))->assertOk()->getContent();
    $class = $this->actingAs($admin)->get(route('results.class-pdf', $examination))->assertOk()->getContent();

    expect($single)->not->toContain('/SMask')
        ->and($class)->not->toContain('/SMask')
        ->and($single)->toContain('/Subtype /Image');
});
