<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultRepository;
use App\Services\ResultTokenIssuer;

/**
 * A parent checking a result from start to finish, the way it happens on a
 * phone: the admission number typed in small letters with spaces, the token
 * read off a slip with a space in the middle, a class with many subjects and
 * an admission number carrying the session ("TGA-2026/2027-JUR-005").
 *
 * Walked on every address a school hands out, on a Standard school (marks
 * read live) and on a Basic one (the card the school published).
 */
function e2eSchool(PlanKey $plan): array
{
    $school = School::factory()->create(['name' => 'Triple Glory Academy', 'result_link_enabled' => true, 'is_active' => true]);
    activateSchool($school, $plan);
    $school->website()->create(['hero_title' => 'Welcome', 'about_text' => 'About', 'is_published' => true]);
    $school->refresh();

    $student = Student::factory()->create([
        'school_id' => $school->id, 'class_name' => 'JSS 3', 'is_active' => true,
        'first_name' => 'Chiamaka', 'last_name' => 'Maduekwe', 'admission_number' => 'TGA-2026/2027-JUR-005',
    ]);
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 3', 'session' => '2026/2027', 'term' => 'first']);
    $examination->subjects()->delete();

    foreach (range(1, 17) as $i) {
        $subject = ExaminationSubject::create(['examination_id' => $examination->id, 'name' => "Subject {$i}", 'max_score' => 100]);
        ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $student->id, 'test_score' => 30, 'exam_score' => 45, 'score' => 75]);
    }

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    app(ResultRepository::class)->publish($examination, $student, $admin, $admin->name);
    $token = app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];

    return [$school, $student, $token];
}

function e2eBase(School $school, string $address): string
{
    return match ($address) {
        'school link' => '/'.$school->result_link_slug.'/result',
        'portal address' => '/p/'.$school->portal_key.'/check-result',
        'school website' => "https://{$school->subdomain}.akademicanest.com/results",
    };
}

beforeEach(function () {
    config(['custom_domain.tenant_base_domain' => 'akademicanest.com', 'app.url' => 'https://akademicanest.com']);
});

test('a parent checks and downloads a result', function (PlanKey $plan, string $address) {
    if ($address === 'school website' && $plan === PlanKey::Basic) {
        // Basic schools have no website; their link is the school link.
        expect(true)->toBeTrue();

        return;
    }

    [$school, $student, $token] = e2eSchool($plan);
    $base = e2eBase($school, $address);
    $host = str_starts_with($base, 'https://') ? '' : '';

    $this->get($base)->assertOk()->assertSee('School ID / Admission Number');

    // Typed on a phone: small letters, a stray space.
    $this->post($base.'/identify', ['admission_number' => ' tga-2026/2027-jur-005 '])->assertRedirect();

    $confirm = str_starts_with($base, 'https://') ? $base.'/confirm' : $base.'/confirm';
    $this->get($confirm)->assertOk()->assertSee('Chiamaka')->assertSee('Exam Token');

    // Read off the slip with a space in the middle.
    $typed = substr($token, 0, 6).' '.substr($token, 6);
    $verify = $this->post($base, ['code' => $typed]);
    $verify->assertRedirect();
    $resultUrl = $verify->headers->get('Location');
    if (! str_starts_with($resultUrl, 'http') && str_starts_with($base, 'https://')) {
        $resultUrl = 'https://'.$school->subdomain.'.akademicanest.com'.$resultUrl;
    }

    $page = $this->get($resultUrl)->assertOk();
    $page->assertSee('Chiamaka')
        ->assertSee('TGA-2026/2027-JUR-005')
        ->assertSee('Subject 17')
        ->assertSee('Download Result');

    $download = $this->get(rtrim($resultUrl, '/').'/download')->assertOk();

    expect($download->headers->get('content-type'))->toContain('application/pdf')
        ->and($download->headers->get('content-disposition'))->toContain('result-TGA-2026-2027-JUR-005.pdf')
        ->and(preg_match_all('#/Type\s*/Page[^s]#', $download->getContent()))->toBe(1);
})->with([
    'standard school' => PlanKey::Standard,
    'basic school' => PlanKey::Basic,
])->with(['school link', 'portal address', 'school website']);

test('a token typed with dashes, and an admission number with spaces inside, still work', function () {
    [$school, $student, $token] = e2eSchool(PlanKey::Standard);
    $base = e2eBase($school, 'school link');

    $this->post($base.'/identify', ['admission_number' => 'tga - 2026/2027 - jur - 005'])->assertRedirect($base.'/confirm');

    $typed = implode('-', str_split($token, 4));
    $this->post($base, ['code' => $typed])->assertRedirect();
    expect(session('errors'))->toBeNull();
});

test('the token box tells a phone not to capitalise or correct it, and gives the right length', function () {
    [$school] = e2eSchool(PlanKey::Standard);
    $base = e2eBase($school, 'school link');

    $this->post($base.'/identify', ['admission_number' => 'TGA-2026/2027-JUR-005']);

    $this->get($base.'/confirm')
        ->assertOk()
        ->assertSee('autocapitalize="none"', false)
        ->assertSee('autocorrect="off"', false)
        ->assertSee('12-character exam token')
        ->assertDontSee('15-character');
});
