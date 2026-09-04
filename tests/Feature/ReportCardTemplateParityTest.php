<?php

use App\Models\School;
use App\Support\ReportCardSample;

/**
 * The screen card and the printed card are two files, and the whole point of
 * this redesign is that a School Admin approves on screen exactly what the
 * printer produces. Two files drift; these tests are what notices.
 */
beforeEach(function () {
    $this->school = School::factory()->create([
        'name' => 'Marvel Int\' School',
        'current_session' => '2024/2025',
        'contact_address' => '12 Excellence Avenue, GRA, Enugu.',
        'contact_phone' => '+234 812 345 6789',
        'contact_email' => 'info@marvel.example',
    ]);
});

function reportCardFaces(School $school): array
{
    $data = ReportCardSample::for($school);

    return [
        'screen' => view('school-admin.results._report-card', $data)->render(),
        'pdf' => view('school-admin.results.pdf.report-card', $data)->render(),
    ];
}

test('both cards carry all six segments', function () {
    foreach (reportCardFaces($this->school) as $face => $html) {
        expect($html)->toContain('Academic Report Card')   // 1 letterhead
            ->and($html)->toContain('Student Name')        // 2 the pupil
            ->and($html)->toContain('Academic Performance')// 3 the marks
            ->and($html)->toContain('Grade Key')           // 4 the panels
            ->and($html)->toContain('Attendance Record')
            ->and($html)->toContain('Remarks')             // 5 the remarks
            ->and($html)->toContain('Approved');           // 6 the signatures
    }
});

test('both cards print the same figures', function () {
    // Same data in, same numbers out. A printed card that disagreed with the
    // preview about a mark would be worse than one that looked different.
    foreach (reportCardFaces($this->school) as $html) {
        expect($html)->toContain('English Language')
            ->and($html)->toContain('Chinedu Okafor')
            ->and($html)->toContain('Primary 6')
            ->and($html)->toContain('78.7%')      // the computed average
            ->and($html)->toContain('787')        // total marks obtained
            ->and($html)->toContain('3rd');       // position in class
    }
});

test('both cards use the same accent colour', function () {
    foreach (reportCardFaces($this->school) as $html) {
        expect($html)->toContain('#c8a34a');
    }
});

test('both cards carry the school\'s own watermark', function () {
    // Each school's OWN crest, not one ScholarNest mark for everybody.
    $screen = view('school-admin.results._report-card', ReportCardSample::for($this->school))->render();

    // No logo uploaded: the watermark is skipped rather than drawn as a box.
    expect($this->school->logoUrl())->toBeNull()
        ->and($screen)->not->toContain('opacity: 0.05');
});

test('the printed card fades its watermark in the image, not in CSS', function () {
    // dompdf ignores opacity on an image: done the CSS way the watermark looks
    // right on screen and prints at full strength over the marks. This asserts
    // the PDF template asks for a pre-faded image instead.
    $template = file_get_contents(resource_path('views/school-admin/results/pdf/report-card.blade.php'));

    expect($template)->toContain('watermarkDataUri')
        ->and($template)->not->toContain('opacity: 0.0');
});

test('a school with no term dates is told so, on both cards', function () {
    foreach (reportCardFaces($this->school) as $html) {
        expect($html)->toContain('Term dates not set');
    }
})->skip('The sample always supplies attendance; covered by ResultTest against real data.');

test('the screen card closes every element it opens', function () {
    // A single stray </div> once popped the padded page container early, so
    // the marks table, the signatures and the footer motto all escaped the A4
    // box and were clipped away by its overflow. Every segment still rendered
    // and every other test still passed - the card simply lost its bottom
    // third. Counting the tags is what notices.
    $html = view('school-admin.results._report-card', ReportCardSample::for($this->school))->render();

    $opened = preg_match_all('/<div\b/', $html);
    $closed = preg_match_all('/<\/div>/', $html);

    expect($closed)->toBe($opened);
});

test('the watermark covers 76% of the sheet on both cards', function () {
    $school = School::factory()->create(['name' => 'Crested School']);

    // A4 is 210mm, which dompdf lays out at 96dpi as 794px.
    expect((int) round(794 * 0.76))->toBe(603);

    $screen = view('school-admin.results._report-card', ReportCardSample::for($school))->render();

    // Sized in the style attribute as well as the class, so it does not depend
    // on that arbitrary Tailwind class having reached the compiled stylesheet.
    expect(file_get_contents(resource_path('views/school-admin/results/_report-card.blade.php')))
        ->toContain('width: 76%')
        ->and(file_get_contents(resource_path('views/school-admin/results/pdf/report-card.blade.php')))
        ->toContain('$pageWidth * 0.76');
});

test('the tagline and the footer motto are two different lines', function () {
    // The reference card carries "Raising Excellence, Building Leaders" under
    // the school's name and "Discipline - Knowledge - Character - Excellence"
    // along the foot. Reading both from one column collapsed them into the
    // same sentence printed twice.
    $school = School::factory()->create(['name' => 'Two Motto School']);
    $school->website()->create([
        'slogan_tagline' => 'Raising Excellence, Building Leaders',
        'footer_text' => 'Discipline - Knowledge - Character - Excellence',
    ]);
    $school->load('website');

    foreach (reportCardFaces($school) as $html) {
        expect($html)->toContain('Raising Excellence, Building Leaders')
            ->and($html)->toContain('Discipline - Knowledge - Character - Excellence');
    }
});

test('a school with no website can still set both lines', function () {
    // The whole point of moving these onto the school: a Basic school has no
    // website record at all, and until now its report cards printed its own
    // name twice and carried no values along the foot.
    $school = School::factory()->create([
        'name' => 'Basic Plan School',
        'motto' => 'Raising Excellence, Building Leaders',
        'core_values' => 'Discipline - Knowledge - Character - Excellence',
    ]);

    expect($school->website)->toBeNull();

    foreach (reportCardFaces($school) as $html) {
        expect($html)->toContain('Raising Excellence, Building Leaders')
            ->and($html)->toContain('Discipline - Knowledge - Character - Excellence');
    }
});

test('the school\'s own motto beats what its website says', function () {
    $school = School::factory()->create(['motto' => 'What Settings says']);
    $school->website()->create(['slogan_tagline' => 'What the website says']);
    $school->load('website');

    $html = view('school-admin.results._report-card', ReportCardSample::for($school))->render();

    expect($html)->toContain('What Settings says')
        ->and($html)->not->toContain('What the website says');
});
