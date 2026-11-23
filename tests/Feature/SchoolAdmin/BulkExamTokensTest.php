<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultTokenBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ExaminationResolver;
use App\Services\ResultTokenIssuer;
use Smalot\PdfParser\Parser;

/**
 * Bulk exam tokens: hand-picked students across several classes, reviewed,
 * generated as one batch, printed on A4.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Basic);
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);

    foreach (['Basic 5', 'Basic 6', 'JSS 1'] as $order => $name) {
        SchoolClass::factory()->create([
            'school_id' => $this->school->id,
            'academic_level_id' => $level->id,
            'name' => $name,
            'sort_order' => $order,
        ]);
    }

    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->roll = collect(['Basic 5' => 12, 'Basic 6' => 16, 'JSS 1' => 21])->map(
        fn (int $count, string $class) => Student::factory()->count($count)->create([
            'school_id' => $this->school->id,
            'class_name' => $class,
            'is_active' => true,
        ])
    );

    $this->session = '2026/2027';
});

/**
 * @param  array<string, int>  $perClass
 * @return array<string, mixed>
 */
function bulkSelection(object $test, array $perClass): array
{
    $ids = [];
    $quantities = [];

    foreach ($perClass as $class => $count) {
        $ids = [...$ids, ...$test->roll[$class]->take($count)->pluck('id')->all()];
        $quantities[] = ['class' => $class, 'count' => $count];
    }

    return [
        'session' => $test->session,
        'term' => ExamTerm::First->value,
        'student_ids' => $ids,
        'quantities' => $quantities,
    ];
}

test('the selection screen lists every class with its students and tick boxes', function () {
    $this->actingAs($this->admin)
        ->get(route('result-pins.bulk.create', ['session' => $this->session, 'term' => 'first']))
        ->assertOk()
        ->assertSee('Choose classes')
        ->assertSee('Select All')
        ->assertSee('Number of students')
        ->assertViewHas('classes', fn ($classes) => $classes->pluck('name')->all() === ['Basic 5', 'Basic 6', 'JSS 1']
            && collect($classes->last()['students'])->pluck('id')->sort()->values()->all() === $this->roll['JSS 1']->pluck('id')->sort()->values()->all());

    // Looking files no examinations.
    expect(Examination::where('school_id', $this->school->id)->count())->toBe(0);
});

test('the review shows exactly the chosen students, by class, with the total, and creates nothing', function () {
    $selection = bulkSelection($this, ['Basic 5' => 10, 'Basic 6' => 15, 'JSS 1' => 20]);

    $response = $this->actingAs($this->admin)
        ->post(route('result-pins.bulk.review'), $selection)
        ->assertOk()
        ->assertSee('Summary')
        ->assertSee('Confirm &amp; Generate', false);

    foreach (['Basic 5' => 10, 'Basic 6' => 15, 'JSS 1' => 20] as $class => $count) {
        $response->assertSee($this->roll[$class]->take($count)->last()->fullName());
        $response->assertDontSee($this->roll[$class]->get($count)->fullName());
    }

    expect(ResultCheckingPin::count())->toBe(0)
        ->and(ResultTokenBatch::count())->toBe(0);
});

test('tokens are generated for exactly the selected students, each bound to their own class examination', function () {
    $selection = bulkSelection($this, ['Basic 5' => 10, 'Basic 6' => 15, 'JSS 1' => 20]);

    $response = $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), $selection);

    $batch = ResultTokenBatch::sole();
    $response->assertRedirect(route('result-pins.batches.show', $batch));

    expect($batch->token_count)->toBe(45)
        ->and($batch->tokens()->count())->toBe(45)
        ->and(ResultCheckingPin::count())->toBe(45);

    $issuedTo = ResultCheckingPin::pluck('bound_student_id')->sort()->values()->all();
    expect($issuedTo)->toBe(collect($selection['student_ids'])->sort()->values()->all());

    // Every token unique, and bound to the examination of its own student's class.
    expect(ResultCheckingPin::pluck('token_hash')->unique()->count())->toBe(45);

    ResultCheckingPin::with(['boundStudent', 'examination'])->get()->each(function (ResultCheckingPin $pin) {
        expect($pin->examination->class_name)->toBe($pin->boundStudent->class_name)
            ->and($pin->examination->term)->toBe(ExamTerm::First)
            ->and($pin->examination->session)->toBe('2026/2027');
    });

    expect(AuditLog::where('action', 'result-token.issued-bulk')->exists())->toBeTrue();
});

test('a student who already holds a valid token is not given a second one', function () {
    $student = $this->roll['Basic 5']->first();
    $examination = app(ExaminationResolver::class)->forTokens($this->school, $this->session, ExamTerm::First, 'Basic 5');
    app(ResultTokenIssuer::class)->issue($this->school, $student, $examination, $this->admin);

    // Shown as already holding one on the selection screen.
    $this->actingAs($this->admin)
        ->get(route('result-pins.bulk.create', ['session' => $this->session, 'term' => 'first']))
        ->assertViewHas('classes', function ($classes) use ($student) {
            $rows = collect($classes->firstWhere('name', 'Basic 5')['students']);

            return $rows->firstWhere('id', $student->id)['has_token'] === true
                && $rows->where('has_token', true)->count() === 1;
        });

    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 5' => 3]))
        ->assertSessionHas('status', fn ($status) => str_contains($status, '1 selected student(s) already held a valid token'));

    expect(ResultCheckingPin::where('bound_student_id', $student->id)->count())->toBe(1)
        ->and(ResultTokenBatch::sole()->token_count)->toBe(2);
});

test('pressing confirm twice does not issue twice', function () {
    $selection = bulkSelection($this, ['Basic 6' => 5]);

    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), $selection);
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), $selection)
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'No tokens were generated'));

    expect(ResultCheckingPin::count())->toBe(5)
        ->and(ResultTokenBatch::count())->toBe(1);
});

test('a revoked or expired token does not stop a new one', function () {
    $examination = app(ExaminationResolver::class)->forTokens($this->school, $this->session, ExamTerm::First, 'JSS 1');
    [$a, $b] = $this->roll['JSS 1']->take(2)->all();

    app(ResultTokenIssuer::class)->issue($this->school, $a, $examination, $this->admin)['token']->update(['status' => ResultCheckingPinStatus::Revoked]);
    app(ResultTokenIssuer::class)->issue($this->school, $b, $examination, $this->admin)['token']->update(['expires_at' => now()->subDay()]);

    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['JSS 1' => 2]));

    expect(ResultTokenBatch::sole()->token_count)->toBe(2);
});

test('the number per class must match the students ticked', function () {
    $selection = bulkSelection($this, ['Basic 5' => 10]);
    $selection['quantities'] = [['class' => 'Basic 5', 'count' => 12]];

    $this->actingAs($this->admin)
        ->post(route('result-pins.bulk.store'), $selection)
        ->assertSessionHasErrors(['quantities' => 'You asked for 12 token(s) for Basic 5 but selected 10 student(s). Tick the students or change the number so they match.']);

    expect(ResultCheckingPin::count())->toBe(0);
});

test('another school\'s students, inactive students and an empty selection are refused', function () {
    $other = Student::factory()->create(['class_name' => 'Basic 5', 'is_active' => true]);
    $left = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Basic 5', 'is_active' => false]);

    foreach ([$other, $left] as $student) {
        $this->actingAs($this->admin)
            ->post(route('result-pins.bulk.review'), [
                'session' => $this->session,
                'term' => 'first',
                'student_ids' => [$student->id],
            ])
            ->assertSessionHasErrors('student_ids');
    }

    $this->actingAs($this->admin)
        ->post(route('result-pins.bulk.store'), ['session' => $this->session, 'term' => 'first', 'student_ids' => []])
        ->assertSessionHasErrors(['student_ids' => 'Select at least one student.']);

    expect(ResultCheckingPin::count())->toBe(0);
});

test('the batch page shows each generated token beside its student', function () {
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 5' => 2, 'JSS 1' => 1]));
    $batch = ResultTokenBatch::sole();

    $response = $this->actingAs($this->admin)->get(route('result-pins.batches.show', $batch))
        ->assertOk()
        ->assertSee('Print All')
        ->assertSee('Print Class')
        ->assertSee('Print Selected')
        ->assertSee('Download PDF');

    foreach ($batch->tokens()->with('boundStudent')->get() as $pin) {
        $response->assertSee($pin->plainToken())->assertSee($pin->boundStudent->admission_number);
    }
});

test('another school cannot open or print a batch', function () {
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 5' => 2]));
    $batch = ResultTokenBatch::sole();

    $outsider = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => activateSchool(School::factory()->create(), PlanKey::Basic)->id,
    ]);

    $this->actingAs($outsider)->get(route('result-pins.batches.show', $batch))->assertForbidden();
    $this->actingAs($outsider)->post(route('result-pins.print'), ['batch' => $batch->uuid])->assertNotFound();
});

/**
 * @return list<string> the text of each page
 */
function pdfPages(string $bytes): array
{
    return array_map(fn ($page) => $page->getText(), (new Parser)->parseContent($bytes)->getPages());
}

test('the A4 printout carries at least 15 students a page and continues onto further pages', function () {
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 5' => 12, 'Basic 6' => 16, 'JSS 1' => 21]));
    $batch = ResultTokenBatch::sole();

    $response = $this->actingAs($this->admin)->post(route('result-pins.print'), ['batch' => $batch->uuid])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $pages = pdfPages($response->getContent());

    // 49 students at 25 a page: two pages, the first one full.
    expect($pages)->toHaveCount(2)
        ->and($pages[0])->toContain('Page 1 of 2')
        ->and($pages[1])->toContain('Page 2 of 2');

    $text = implode("\n", $pages);

    foreach ($batch->tokens()->with('boundStudent')->get() as $pin) {
        expect($text)->toContain($pin->plainToken())
            ->and($text)->toContain($pin->boundStudent->admission_number);
    }

    // At least fifteen students' tokens on the first page.
    $onFirst = $batch->tokens->filter(fn ($pin) => str_contains($pages[0], $pin->plainToken()))->count();
    expect($onFirst)->toBeGreaterThanOrEqual(15)->toBe(25);

    expect(AuditLog::where('action', 'result-token.printed')->exists())->toBeTrue();
});

test('a class, or chosen students, can be printed on their own, and downloaded', function () {
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 5' => 4, 'JSS 1' => 3]));
    $batch = ResultTokenBatch::sole();
    $tokens = $batch->tokens()->with('boundStudent')->get();

    $classPdf = implode("\n", pdfPages($this->actingAs($this->admin)
        ->post(route('result-pins.print'), ['batch' => $batch->uuid, 'class_name' => 'JSS 1'])
        ->getContent()));

    foreach ($tokens as $pin) {
        $pin->boundStudent->class_name === 'JSS 1'
            ? expect($classPdf)->toContain($pin->plainToken())
            : expect($classPdf)->not->toContain($pin->plainToken());
    }

    $chosen = $tokens->take(2);
    $response = $this->actingAs($this->admin)->post(route('result-pins.print'), [
        'batch' => $batch->uuid,
        'tokens' => $chosen->pluck('uuid')->all(),
        'download' => 1,
    ]);

    expect($response->headers->get('Content-Disposition'))->toContain('attachment');

    $chosenPdf = implode("\n", pdfPages($response->getContent()));

    foreach ($tokens as $pin) {
        $chosen->contains($pin)
            ? expect($chosenPdf)->toContain($pin->plainToken())
            : expect($chosenPdf)->not->toContain($pin->plainToken());
    }
});

test('revoked tokens are left out of a printout, and a reissued one stays in its batch', function () {
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 6' => 3]));
    $batch = ResultTokenBatch::sole();
    [$revoked, $reissued] = $batch->tokens()->get()->all();

    $revoked->update(['status' => ResultCheckingPinStatus::Revoked]);
    $fresh = app(ResultTokenIssuer::class)->reissue($reissued, $this->admin);

    $text = implode("\n", pdfPages($this->actingAs($this->admin)->post(route('result-pins.print'), ['batch' => $batch->uuid])->getContent()));

    expect($text)->not->toContain($revoked->plainToken())
        ->and($text)->not->toContain($reissued->plainToken())
        ->and($text)->toContain($fresh['plain'])
        ->and($fresh['token']->batch_id)->toBe($batch->id);
});

test('every valid token for a term can be printed, however it was issued', function () {
    $examination = app(ExaminationResolver::class)->forTokens($this->school, $this->session, ExamTerm::First, 'Basic 5');
    $single = app(ResultTokenIssuer::class)->issue($this->school, $this->roll['Basic 5']->first(), $examination, $this->admin);

    $text = implode("\n", pdfPages($this->actingAs($this->admin)
        ->post(route('result-pins.print'), ['session' => $this->session, 'term' => 'first'])
        ->assertOk()
        ->getContent()));

    expect($text)->toContain($single['plain']);

    $this->actingAs($this->admin)
        ->from(route('result-pins.index'))
        ->post(route('result-pins.print'), ['session' => $this->session, 'term' => 'second'])
        ->assertRedirect(route('result-pins.index'))
        ->assertSessionHasErrors('print');
});

test('the exam token page links to the bulk flow and recent batches', function () {
    $this->actingAs($this->admin)->post(route('result-pins.bulk.store'), bulkSelection($this, ['Basic 5' => 2]));

    $this->actingAs($this->admin)
        ->get(route('result-pins.index'))
        ->assertOk()
        ->assertSee('Bulk exam tokens')
        ->assertSee(route('result-pins.bulk.create'), false)
        ->assertSee(route('result-pins.batches.show', ResultTokenBatch::sole()), false)
        ->assertSee('Print tokens for a term');
});
