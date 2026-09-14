<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\PrincipalRemark;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\ReportCardSample;

/**
 * The Principal's library of reusable remarks.
 *
 * A head writes the same handful of sentences several hundred times a term, so
 * they keep the ones they use. Two properties matter more than the CRUD:
 *
 *   1. The library is the SCHOOL'S. One school can never read, reword or
 *      delete another's, and nothing in a request can point at one.
 *   2. A remark on a card is a COPY, not a reference. Rewording a library
 *      entry, or deleting it, never rewrites a result already issued,
 *      because what was said about a child on the day is not a row that can
 *      change underneath them.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Remark School']), PlanKey::Basic);
    $this->principal = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('the Principal saves a remark, and there is no limit on how many', function () {
    $this->actingAs($this->principal);

    foreach (range(1, 25) as $index) {
        $this->post(route('results.remark-library.store'), ['body' => "Remark number {$index}."])
            ->assertRedirect();
    }

    expect(PrincipalRemark::where('school_id', $this->school->id)->count())->toBe(25);
});

test('saving the same sentence twice keeps one entry', function () {
    $this->actingAs($this->principal);

    // Normalised before hashing, so spacing and case are not two remarks.
    $this->post(route('results.remark-library.store'), ['body' => 'Excellent performance. Keep it up.'])->assertRedirect();
    $this->post(route('results.remark-library.store'), ['body' => '  Excellent   performance.  Keep it up.  '])->assertRedirect();
    $this->post(route('results.remark-library.store'), ['body' => 'EXCELLENT PERFORMANCE. KEEP IT UP.'])->assertRedirect();

    expect(PrincipalRemark::where('school_id', $this->school->id)->count())->toBe(1);
});

test('the Principal rewords and deletes their own remarks', function () {
    $remark = PrincipalRemark::saveFor($this->school, 'Good improvement this term.', $this->principal);

    $this->actingAs($this->principal)
        ->put(route('results.remark-library.update', $remark), ['body' => 'Very good improvement this term.'])
        ->assertRedirect();

    expect($remark->fresh()->body)->toBe('Very good improvement this term.')
        // The hash moves with the text, or saving the old wording again would
        // silently collide with this row.
        ->and($remark->fresh()->body_hash)->toBe(hash('sha256', 'very good improvement this term.'));

    $this->actingAs($this->principal)
        ->delete(route('results.remark-library.destroy', $remark))
        ->assertRedirect();

    expect(PrincipalRemark::find($remark->id))->toBeNull();
});

describe('one school never touches another\'s library', function () {
    beforeEach(function () {
        $this->otherSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
        $this->otherPrincipal = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->otherSchool->id]);
        $this->theirRemark = PrincipalRemark::saveFor($this->otherSchool, 'Their private wording.', $this->otherPrincipal);
    });

    test('nor reads it', function () {
        PrincipalRemark::saveFor($this->school, 'Our own wording.', $this->principal);

        $this->actingAs($this->principal)
            ->get(route('results.remark-library.index'))
            ->assertOk()
            ->assertSee('Our own wording.')
            ->assertDontSee('Their private wording.');
    });

    test('nor rewords it, even knowing its uuid', function () {
        $this->actingAs($this->principal)
            ->put(route('results.remark-library.update', $this->theirRemark), ['body' => 'Hijacked.'])
            ->assertForbidden();

        expect($this->theirRemark->fresh()->body)->toBe('Their private wording.');
    });

    test('nor deletes it', function () {
        $this->actingAs($this->principal)
            ->delete(route('results.remark-library.destroy', $this->theirRemark))
            ->assertForbidden();

        expect(PrincipalRemark::find($this->theirRemark->id))->not->toBeNull();
    });

    test('nor sees it in the picker on a pupil\'s result', function () {
        $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
        $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

        $response = $this->actingAs($this->principal)
            ->getJson(route('results.show', [$examination, $student]));

        $response->assertOk();

        expect($response->json('details_html'))->not->toContain('Their private wording.');
    });
});

describe('assigning a remark to a pupil', function () {
    beforeEach(function () {
        $this->examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
        $this->students = Student::factory()->count(3)->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    });

    test('each pupil keeps their own', function () {
        $wordings = [
            'Excellent performance. Keep it up.',
            'Good result, but more effort is required.',
            'Outstanding improvement this term.',
        ];

        foreach ($this->students as $index => $student) {
            $this->actingAs($this->principal)
                ->putJson(route('results.remarks', [$this->examination, $student]), [
                    'principal_remark' => $wordings[$index],
                ])
                ->assertOk();
        }

        foreach ($this->students as $index => $student) {
            $report = ExaminationReport::where('examination_id', $this->examination->id)
                ->where('student_id', $student->id)
                ->firstOrFail();

            expect($report->principal_remark)->toBe($wordings[$index]);
        }
    });

    test('the remark is copied, so rewording the library leaves cards alone', function () {
        $remark = PrincipalRemark::saveFor($this->school, 'Outstanding performance.', $this->principal);
        $student = $this->students->first();

        $this->actingAs($this->principal)
            ->putJson(route('results.remarks', [$this->examination, $student]), [
                'principal_remark' => $remark->body,
            ])
            ->assertOk();

        $remark->reword('Something else entirely.');

        $report = ExaminationReport::where('student_id', $student->id)->firstOrFail();

        expect($report->principal_remark)->toBe('Outstanding performance.');
    });

    test('deleting the library entry leaves the card alone too', function () {
        $remark = PrincipalRemark::saveFor($this->school, 'Keep up the good work.', $this->principal);
        $student = $this->students->first();

        $this->actingAs($this->principal)
            ->putJson(route('results.remarks', [$this->examination, $student]), ['principal_remark' => $remark->body])
            ->assertOk();

        $remark->delete();

        expect(ExaminationReport::where('student_id', $student->id)->firstOrFail()->principal_remark)
            ->toBe('Keep up the good work.');
    });

    test('a new remark can be saved to the library on the way past', function () {
        $student = $this->students->first();

        $this->actingAs($this->principal)
            ->putJson(route('results.remarks', [$this->examination, $student]), [
                'principal_remark' => 'A brand new sentence for this pupil.',
                'save_principal_remark' => true,
            ])
            ->assertOk();

        expect(PrincipalRemark::where('school_id', $this->school->id)->where('body', 'A brand new sentence for this pupil.')->exists())->toBeTrue();
    });

    test('writing one WITHOUT ticking the box does not fill the library', function () {
        // A one-off sentence about one child is not a template.
        $student = $this->students->first();

        $this->actingAs($this->principal)
            ->putJson(route('results.remarks', [$this->examination, $student]), [
                'principal_remark' => 'A private note about this child only.',
            ])
            ->assertOk();

        expect(PrincipalRemark::where('school_id', $this->school->id)->count())->toBe(0);
    });

    test('the administrator who wrote it is recorded, and when', function () {
        $student = $this->students->first();

        $this->actingAs($this->principal)
            ->putJson(route('results.remarks', [$this->examination, $student]), ['principal_remark' => 'Well done.'])
            ->assertOk();

        $report = ExaminationReport::where('student_id', $student->id)->firstOrFail();

        expect($report->principal_remark_by)->toBe($this->principal->id)
            ->and($report->principal_remark_at)->not->toBeNull();
    });

    test('the Principal\'s remark is independent of the Class Teacher\'s', function () {
        $student = $this->students->first();

        $this->actingAs($this->principal)
            ->putJson(route('results.remarks', [$this->examination, $student]), [
                'teacher_remark' => 'Written by the class teacher.',
                'principal_remark' => 'Written by the principal.',
            ])
            ->assertOk();

        $report = ExaminationReport::where('student_id', $student->id)->firstOrFail();

        expect($report->teacher_remark)->toBe('Written by the class teacher.')
            ->and($report->principal_remark)->toBe('Written by the principal.');
    });
});

test('both remarks print in italic on the report card', function () {
    $data = ReportCardSample::for($this->school);
    $data['report']->teacher_remark = 'Excellent effort this term.';
    $data['report']->principal_remark = 'Outstanding performance.';

    foreach (['school-admin.results._report-card', 'school-admin.results.pdf.report-card'] as $template) {
        $html = view($template, $data)->render();

        // The label is bold, the wording italic, each remark sits in a cell
        // that declares italic and carries the text.
        expect($html)->toMatch('/italic[^>]*>\s*Excellent effort this term\./')
            ->and($html)->toMatch('/italic[^>]*>\s*Outstanding performance\./');
    }
});

test('the picker offers the saved remarks on a pupil\'s result', function () {
    PrincipalRemark::saveFor($this->school, 'Outstanding academic performance.', $this->principal);

    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $response = $this->actingAs($this->principal)
        ->getJson(route('results.show', [$examination, $student]));

    $response->assertOk();

    expect($response->json('details_html'))
        ->toContain('Insert a saved remark')
        ->toContain('Outstanding academic performance.')
        // Writing a new one directly is never blocked by the picker.
        ->toContain('result-principal-remark');
});
