<?php

use App\Enums\EmploymentType;
use App\Enums\InterviewMode;
use App\Enums\JobApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\JobQuestionType;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Http\Middleware\RejectBotSubmissions;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobInterview;
use App\Models\JobPosting;
use App\Models\School;
use App\Models\User;
use App\Notifications\JobApplicationReceivedNotification;
use App\Notifications\JobInterviewInvitationNotification;
use App\Notifications\NewJobApplicationNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UploadFixtures;

/**
 * The school Job Portal, end to end: a School Admin creates and publishes a
 * vacancy, shares its link, an applicant applies from a phone, the school is
 * notified, reviews, and invites to interview, with every school's jobs,
 * applicants and files kept away from every other school.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = activateSchool(School::factory()->create([
        'name' => 'Greenfield College',
        'contact_address' => '12 Allen Avenue, Ikeja, Lagos',
        'contact_phone' => '0803 000 1111',
        'contact_email' => 'office@greenfield.test',
        'timezone' => 'Africa/Lagos',
    ]), PlanKey::Standard);

    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->otherSchool = activateSchool(School::factory()->create(['name' => 'Hilltop Academy']), PlanKey::Standard);
    $this->otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->otherSchool->id]);
});

function vacancyFields(array $overrides = []): array
{
    return [
        'title' => 'Mathematics Teacher',
        'department' => 'Sciences',
        'employment_type' => EmploymentType::FullTime->value,
        'location' => 'Main campus, Ikeja',
        'openings' => 2,
        'salary_range' => '₦150,000 to ₦200,000 per month',
        'closes_at' => today()->addWeeks(3)->format('Y-m-d'),
        'description' => 'Teach Mathematics to senior secondary classes.',
        'responsibilities' => "Teach SS1 to SS3\nPrepare lesson notes",
        'requirements' => 'Strong classroom management.',
        'qualifications' => 'B.Sc./B.Ed. Mathematics',
        'experience_required' => 'At least 3 years',
        'application_instructions' => 'Attach your TRCN certificate.',
        'contact_name' => 'Mrs Ada Obi',
        'contact_email' => 'hr@greenfield.test',
        'contact_phone' => '0803 222 3333',
        ...$overrides,
    ];
}

function cvPdf(string $name = 'Ada CV.pdf'): UploadedFile
{
    return UploadFixtures::file("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n", $name, 'application/pdf');
}

function applicationFields(array $overrides = []): array
{
    return [
        'full_name' => 'Chidinma Okafor',
        'email' => 'chidinma@example.test',
        'phone' => '0806 555 1234',
        'address' => '4 Adeola Street, Yaba',
        'cover_letter' => 'I have taught Mathematics for six years and would love to join Greenfield College.',
        'qualifications' => 'B.Ed. Mathematics, University of Lagos',
        'years_of_experience' => 6,
        'cv' => cvPdf(),
        ...$overrides,
    ];
}

function publishedVacancy(School $school, array $attributes = []): JobPosting
{
    return JobPosting::factory()->create(['school_id' => $school->id, 'title' => 'Mathematics Teacher', ...$attributes]);
}

describe('both Standard and Exclusive plans recruit', function () {
    test('Standard and Exclusive School Admins reach Recruitment; Basic does not', function (PlanKey $plan, bool $allowed) {
        $school = activateSchool(School::factory()->create(), $plan);
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get(route('careers.index'));

        $allowed ? $response->assertOk() : $response->assertForbidden();
    })->with([
        'Standard' => [PlanKey::Standard, true],
        'Exclusive' => [PlanKey::Exclusive, true],
        'Basic' => [PlanKey::Basic, false],
    ]);

    test('a school without the plan has no Job Portal', function () {
        $basic = activateSchool(School::factory()->create(), PlanKey::Basic);
        $job = publishedVacancy($basic);

        $this->get(route('public.jobs.index', $basic))->assertNotFound();
        $this->get(route('public.jobs.show', ['school' => $basic, 'token' => $job->public_token]))->assertNotFound();
    });
});

describe('creating and managing vacancies', function () {
    test('a vacancy is created with every field, a job image and extra questions, as a draft', function () {
        $this->actingAs($this->admin)
            ->post(route('careers.store'), vacancyFields([
                'featured_image' => UploadFixtures::cameraJpeg(1, 1600, 900),
                'school_id' => $this->otherSchool->id,
                'questions' => [
                    ['question' => 'Are you TRCN registered?', 'type' => JobQuestionType::YesNo->value, 'required' => '1'],
                    ['question' => 'Which level do you prefer?', 'type' => JobQuestionType::Choice->value, 'options' => 'Junior, Senior', 'required' => '0'],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $job = JobPosting::sole();

        expect($job->school_id)->toBe($this->school->id)
            ->and($job->status)->toBe(JobPostingStatus::Draft)
            ->and($job->public_token)->toHaveLength(32)
            ->and($job->salary_range)->toBe('₦150,000 to ₦200,000 per month')
            ->and($job->featured_image_path)->not->toBeNull()
            ->and($job->questions)->toHaveCount(2)
            ->and($job->questions[1]->options)->toBe(['Junior', 'Senior']);

        Storage::disk('public')->assertExists($job->featured_image_path);

        // A draft is not on the portal.
        $this->get($job->publicUrl())->assertNotFound();
    });

    test('create and publish in one step puts it on the portal', function () {
        $this->actingAs($this->admin)
            ->post(route('careers.store'), vacancyFields(['intent' => 'publish']))
            ->assertSessionHasNoErrors();

        $job = JobPosting::sole();

        expect($job->status)->toBe(JobPostingStatus::Published)
            ->and($job->published_at)->not->toBeNull();

        $this->get(route('public.jobs.index', $this->school))->assertOk()->assertSee('Mathematics Teacher');
        $this->get($job->publicUrl())->assertOk()->assertSee('Mathematics Teacher')->assertSee('Greenfield College');
    });

    test('a deadline in the past is refused', function () {
        $this->actingAs($this->admin)
            ->post(route('careers.store'), vacancyFields(['closes_at' => today()->subDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('closes_at');
    });

    test('the listing shows the school logo, name and address from its profile, and the job details', function () {
        $this->school->update(['logo_path' => 'school-logos/greenfield.png']);
        $job = publishedVacancy($this->school, vacancyFields(['closes_at' => today()->addWeeks(2)]));

        $this->get($job->publicUrl())
            ->assertOk()
            ->assertSee('Greenfield College')
            ->assertSee('12 Allen Avenue, Ikeja, Lagos')
            ->assertSee('office@greenfield.test')
            ->assertSee('school-logos/greenfield.png')
            ->assertSee('₦150,000 to ₦200,000 per month')
            ->assertSee('Teach SS1 to SS3')
            ->assertSee('B.Sc./B.Ed. Mathematics')
            ->assertSee('At least 3 years')
            ->assertSee('Attach your TRCN certificate.')
            ->assertSee('Mrs Ada Obi');
    });

    test('editing keeps a question it edits, and drops one it removes', function () {
        $job = publishedVacancy($this->school);
        $keep = $job->questions()->create(['question' => 'Old wording', 'type' => JobQuestionType::ShortText, 'sort_order' => 0]);
        $job->questions()->create(['question' => 'Removed', 'type' => JobQuestionType::ShortText, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->put(route('careers.update', $job), vacancyFields([
                'closes_at' => $job->closes_at->format('Y-m-d'),
                'questions' => [['id' => $keep->id, 'question' => 'New wording', 'type' => JobQuestionType::LongText->value]],
            ]))
            ->assertSessionHasNoErrors();

        $questions = $job->fresh()->questions;

        expect($questions)->toHaveCount(1)
            ->and($questions[0]->id)->toBe($keep->id)
            ->and($questions[0]->question)->toBe('New wording');
    });

    test('an expired vacancy can still be edited without changing its passed deadline', function () {
        $job = publishedVacancy($this->school, ['closes_at' => today()->subDays(3)]);

        $this->actingAs($this->admin)
            ->put(route('careers.update', $job), vacancyFields(['title' => 'Renamed', 'closes_at' => $job->closes_at->format('Y-m-d')]))
            ->assertSessionHasNoErrors();

        expect($job->fresh()->title)->toBe('Renamed');
    });

    test('publish, unpublish, close, reopen, extend and archive', function () {
        $job = JobPosting::factory()->draft()->create(['school_id' => $this->school->id]);

        $this->actingAs($this->admin)->post(route('careers.publish', $job));
        expect($job->fresh()->status)->toBe(JobPostingStatus::Published);

        $this->actingAs($this->admin)->post(route('careers.unpublish', $job));
        expect($job->fresh()->status)->toBe(JobPostingStatus::Draft);

        $this->actingAs($this->admin)->post(route('careers.publish', $job));
        $this->actingAs($this->admin)->post(route('careers.close', $job));
        expect($job->fresh()->status)->toBe(JobPostingStatus::Closed)
            ->and($job->fresh()->closed_at)->not->toBeNull();

        $this->actingAs($this->admin)->post(route('careers.reopen', $job));
        expect($job->fresh()->status)->toBe(JobPostingStatus::Published);

        $newDeadline = today()->addMonths(2)->format('Y-m-d');
        $this->actingAs($this->admin)->post(route('careers.extend', $job), ['closes_at' => $newDeadline])->assertSessionHasNoErrors();
        expect($job->fresh()->closes_at->format('Y-m-d'))->toBe($newDeadline);

        $this->actingAs($this->admin)->post(route('careers.archive', $job));
        expect($job->fresh()->status)->toBe(JobPostingStatus::Archived);
        $this->get($job->publicUrl())->assertNotFound();
    });

    test('a vacancy past its deadline cannot be reopened until the deadline is extended', function () {
        $job = JobPosting::factory()->closed()->create(['school_id' => $this->school->id, 'closes_at' => today()->subDay()]);

        $this->actingAs($this->admin)->post(route('careers.reopen', $job))->assertSessionHas('error');
        expect($job->fresh()->status)->toBe(JobPostingStatus::Closed);
    });

    test('a vacancy with applications is archived rather than deleted; one without can be deleted', function () {
        $withApplicants = publishedVacancy($this->school);
        JobApplication::factory()->create(['job_posting_id' => $withApplicants->id]);
        $empty = publishedVacancy($this->school, ['title' => 'Unused']);

        $this->actingAs($this->admin)->delete(route('careers.destroy', $withApplicants))->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('careers.destroy', $empty))->assertRedirect(route('careers.index'));

        expect(JobPosting::find($withApplicants->id))->not->toBeNull()
            ->and(JobPosting::find($empty->id))->toBeNull();
    });

    test('the preview shows a draft exactly as applicants will see it', function () {
        $job = JobPosting::factory()->draft()->create(['school_id' => $this->school->id, 'title' => 'Draft Role']);

        $this->actingAs($this->admin)->get(route('careers.preview', $job))
            ->assertOk()
            ->assertSee('Draft Role')
            ->assertSee('Preview: this is how applicants see this vacancy');
    });

    test('the dashboard pages render', function () {
        $job = publishedVacancy($this->school);
        $application = JobApplication::factory()->create(['job_posting_id' => $job->id]);

        $this->actingAs($this->admin)->get(route('careers.index'))->assertOk()->assertSee('Mathematics Teacher');
        $this->actingAs($this->admin)->get(route('careers.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('careers.edit', $job))->assertOk();
        $this->actingAs($this->admin)->get(route('careers.show', $job))->assertOk()->assertSee($job->publicUrl(), false)->assertSee($application->full_name);
        $this->actingAs($this->admin)->get(route('careers.applications.index'))->assertOk()->assertSee($application->full_name);
    });
});

describe('sharing a vacancy', function () {
    test('its link opens that vacancy on the school\'s own Job Portal', function () {
        $job = publishedVacancy($this->school);

        expect($job->publicUrl())->toContain('/jobs/'.$job->public_token)
            ->and($job->publicUrl())->toContain($this->school->portal_key);

        $this->get($job->publicUrl())->assertOk()->assertSee('Mathematics Teacher');
    });

    test('the page carries link-preview tags with the job, school and image', function () {
        $job = publishedVacancy($this->school);

        $this->get($job->publicUrl())
            ->assertSee('<meta property="og:title" content="Mathematics Teacher at Greenfield College">', false)
            ->assertSee('property="og:image" content="'.route('public.jobs.preview-image', ['school' => $this->school, 'token' => $job->public_token]), false)
            ->assertSee('<meta property="og:url" content="'.$job->publicUrl().'">', false)
            ->assertSee('twitter:card" content="summary_large_image"', false);
    });

    test('the preview card and the downloadable share image are real images of the right size', function () {
        $job = publishedVacancy($this->school, ['featured_image_path' => null]);

        $card = $this->get(route('public.jobs.preview-image', ['school' => $this->school, 'token' => $job->public_token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        expect(array_slice(getimagesizefromstring($card->getContent()), 0, 2))->toBe([1200, 630]);

        $story = $this->actingAs($this->admin)->get(route('careers.share-image', $job))->assertOk();

        expect($story->headers->get('Content-Disposition'))->toContain('mathematics-teacher-job-post.jpg')
            ->and(array_slice(getimagesizefromstring($story->getContent()), 0, 2))->toBe([1080, 1350]);
    });

    test('the share image uses the uploaded job image and school logo when there are ones', function () {
        Storage::disk('public')->put('school-logos/logo.png', file_get_contents(UploadFixtures::transparentPng()->getRealPath()));
        Storage::disk('public')->put('job-postings/photo.jpg', file_get_contents(UploadFixtures::cameraJpeg(1, 1600, 900)->getRealPath()));
        $this->school->update(['logo_path' => 'school-logos/logo.png']);
        $job = publishedVacancy($this->school, ['featured_image_path' => 'job-postings/photo.jpg']);

        $this->actingAs($this->admin)->get(route('careers.share-image', $job))->assertOk();
    });
});

describe('applying', function () {
    beforeEach(function () {
        Notification::fake();

        $this->job = publishedVacancy($this->school);
        $this->job->questions()->create(['question' => 'Are you TRCN registered?', 'type' => JobQuestionType::YesNo, 'is_required' => true, 'sort_order' => 0]);
        $this->applyUrl = route('public.jobs.apply', ['school' => $this->school, 'token' => $this->job->public_token]);
    });

    test('an applicant applies with a CV, documents and answers; the school is notified and the applicant emailed', function () {
        $question = $this->job->questions->first();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36'])
            ->post($this->applyUrl, applicationFields([
                'documents' => [UploadFixtures::cameraJpeg(1, 800, 600, 'TRCN.jpg')],
                'answers' => [$question->id => 'Yes'],
                'school_id' => $this->otherSchool->id,
                'status' => 'hired',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->job->publicUrl().'#application');

        $application = JobApplication::sole();

        expect($application->school_id)->toBe($this->school->id)
            ->and($application->job_posting_id)->toBe($this->job->id)
            ->and($application->status)->toBe(JobApplicationStatus::New)
            ->and($application->answers)->toBe([['question' => 'Are you TRCN registered?', 'answer' => 'Yes']])
            ->and($application->cv_original_name)->toBe('Ada CV.pdf')
            ->and($application->documents)->toHaveCount(1)
            ->and($application->events)->toHaveCount(1);

        // Private files, under generated names.
        Storage::disk('local')->assertExists($application->cv_path);
        Storage::disk('public')->assertMissing($application->cv_path);
        expect($application->cv_path)->not->toContain('Ada CV');

        Notification::assertSentTo($this->admin, NewJobApplicationNotification::class, function ($notification) use ($application) {
            $data = $notification->toArray($this->admin);

            return $data['applicant_name'] === 'Chidinma Okafor'
                && $data['position'] === 'Mathematics Teacher'
                && $data['applicant_email'] === 'chidinma@example.test'
                && $data['applicant_phone'] === '0806 555 1234'
                && $data['status'] === 'New'
                && filled($data['submitted_at'])
                && $data['url'] === route('careers.applications.show', $application);
        });

        Notification::assertNotSentTo($this->otherAdmin, NewJobApplicationNotification::class);
        Notification::assertSentOnDemand(JobApplicationReceivedNotification::class, fn ($n, $channels, AnonymousNotifiable $to) => array_key_exists('chidinma@example.test', $to->routes['mail']));

        $this->get($this->job->publicUrl())->assertSee('Application submitted', false);
    });

    test('required fields are enforced', function () {
        $this->post($this->applyUrl, [])
            ->assertSessionHasErrors(['full_name', 'email', 'phone', 'cover_letter', 'qualifications', 'years_of_experience', 'cv', 'answers.'.$this->job->questions->first()->id]);

        expect(JobApplication::count())->toBe(0);
    });

    test('a CV that is not a PDF or Word document is refused, whatever its name', function () {
        $question = $this->job->questions->first();

        $this->post($this->applyUrl, applicationFields([
            'cv' => UploadFixtures::file("<?php system(\$_GET['c']); ?>", 'cv.pdf', 'application/pdf'),
            'answers' => [$question->id => 'Yes'],
        ]))->assertSessionHasErrors('cv');

        expect(JobApplication::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    test('a choice answer must be one of the options', function () {
        $question = $this->job->questions->first();

        $this->post($this->applyUrl, applicationFields(['answers' => [$question->id => 'Maybe']]))
            ->assertSessionHasErrors('answers.'.$question->id);
    });

    test('the same email cannot apply twice for one vacancy', function () {
        $question = $this->job->questions->first();

        $this->post($this->applyUrl, applicationFields(['answers' => [$question->id => 'Yes']]))->assertSessionHasNoErrors();
        $this->post($this->applyUrl, applicationFields(['answers' => [$question->id => 'Yes'], 'email' => 'CHIDINMA@example.test']))->assertSessionHasErrors('email');

        expect(JobApplication::count())->toBe(1);
    });

    test('a closed vacancy, or one past its deadline, takes no applications', function (string $state) {
        $question = $this->job->questions->first();

        $state === 'closed'
            ? $this->job->update(['status' => JobPostingStatus::Closed])
            : $this->job->update(['closes_at' => today()->subDay()]);

        $this->post($this->applyUrl, applicationFields(['answers' => [$question->id => 'Yes']]))
            ->assertRedirect($this->job->publicUrl())
            ->assertSessionHas('job_application_error');

        expect(JobApplication::count())->toBe(0);

        $this->get($this->job->publicUrl())->assertOk()->assertSee('Applications are closed');
    })->with(['closed', 'expired']);

    test('a bot filling the hidden field is turned away', function () {
        $question = $this->job->questions->first();

        $this->post($this->applyUrl, applicationFields(['answers' => [$question->id => 'Yes'], RejectBotSubmissions::FIELD => 'http://spam.example']));

        expect(JobApplication::count())->toBe(0);
    });
});

describe('reviewing applicants', function () {
    beforeEach(function () {
        $this->job = publishedVacancy($this->school);
        Storage::disk('local')->put('job-applications/cv.pdf', '%PDF-1.4 test cv');
        $this->application = JobApplication::factory()->create([
            'job_posting_id' => $this->job->id,
            'full_name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.test',
            'cv_path' => 'job-applications/cv.pdf',
            'cv_original_name' => 'my cv.pdf',
        ]);
    });

    test('opening a new application moves it to Under Review, recorded', function () {
        $this->actingAs($this->admin)->get(route('careers.applications.show', $this->application))
            ->assertOk()
            ->assertSee('Chidinma Okafor')
            ->assertSee($this->application->cover_letter);

        expect($this->application->fresh()->status)->toBe(JobApplicationStatus::UnderReview)
            ->and($this->application->events()->where('to_status', 'under_review')->exists())->toBeTrue();
    });

    test('the status can be changed to any status at any time', function (JobApplicationStatus $status) {
        $this->actingAs($this->admin)
            ->put(route('careers.applications.status', $this->application), ['status' => $status->value, 'note' => 'Decided at panel'])
            ->assertSessionHasNoErrors();

        expect($this->application->fresh()->status)->toBe($status);
    })->with(JobApplicationStatus::cases());

    test('an interview invitation is scheduled in the school\'s time and emailed to the applicant', function () {
        Notification::fake();

        $date = today('Africa/Lagos')->addDays(5)->format('Y-m-d');

        $this->actingAs($this->admin)
            ->post(route('careers.applications.interview', $this->application), [
                'interview_date' => $date,
                'interview_time' => '10:30',
                'mode' => InterviewMode::Physical->value,
                'location' => "Principal's office",
                'instructions' => 'Bring your original certificates.',
                'message' => 'We look forward to meeting you.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $interview = JobInterview::sole();

        expect($interview->school_id)->toBe($this->school->id)
            ->and($interview->localScheduledFor()->format('Y-m-d H:i'))->toBe($date.' 10:30')
            ->and($interview->notified_at)->not->toBeNull()
            ->and($this->application->fresh()->status)->toBe(JobApplicationStatus::InterviewInvited);

        Notification::assertSentOnDemand(JobInterviewInvitationNotification::class, function ($notification, $channels, AnonymousNotifiable $to) use ($date) {
            $mail = (string) $notification->toMail($to)->render();

            return array_key_exists('chidinma@example.test', $to->routes['mail'])
                && str_contains($mail, Carbon::parse($date)->format('l, j F Y'))
                && str_contains($mail, '10:30 AM')
                && str_contains($mail, 'office')
                && str_contains($mail, 'Bring your original certificates.');
        });
    });

    test('a physical interview needs a location; an online one needs a link', function () {
        $base = ['interview_date' => today()->addDays(3)->format('Y-m-d'), 'interview_time' => '09:00'];

        $this->actingAs($this->admin)->post(route('careers.applications.interview', $this->application), [...$base, 'mode' => 'physical'])->assertSessionHasErrors('location');
        $this->actingAs($this->admin)->post(route('careers.applications.interview', $this->application), [...$base, 'mode' => 'online'])->assertSessionHasErrors('meeting_link');
    });

    test('the CV downloads for the school, named for the applicant', function () {
        $response = $this->actingAs($this->admin)->get(route('careers.applications.cv', $this->application))->assertOk();

        expect($response->headers->get('Content-Disposition'))->toContain('Chidinma Okafor CV.pdf')
            ->and($response->streamedContent())->toBe('%PDF-1.4 test cv');
    });
});

describe('school isolation', function () {
    beforeEach(function () {
        $this->job = publishedVacancy($this->school);
        Storage::disk('local')->put('job-applications/cv.pdf', 'cv');
        $this->application = JobApplication::factory()->create(['job_posting_id' => $this->job->id, 'cv_path' => 'job-applications/cv.pdf']);
        $this->document = JobApplicationDocument::create(['job_application_id' => $this->application->id, 'path' => 'job-applications/doc.pdf', 'original_name' => 'doc.pdf']);
    });

    test('another school\'s admin cannot see or change this school\'s vacancy or applicants', function () {
        $as = $this->actingAs($this->otherAdmin);

        $as->get(route('careers.show', $this->job))->assertForbidden();
        $as->get(route('careers.edit', $this->job))->assertForbidden();
        $as->put(route('careers.update', $this->job), vacancyFields())->assertForbidden();
        $as->post(route('careers.close', $this->job))->assertForbidden();
        $as->delete(route('careers.destroy', $this->job))->assertForbidden();
        $as->get(route('careers.share-image', $this->job))->assertForbidden();
        $as->get(route('careers.applications.show', $this->application))->assertForbidden();
        $as->put(route('careers.applications.status', $this->application), ['status' => 'hired'])->assertForbidden();
        $as->post(route('careers.applications.interview', $this->application), [])->assertForbidden();
        $as->get(route('careers.applications.cv', $this->application))->assertForbidden();
        $as->get(route('careers.applications.document', $this->document))->assertForbidden();

        expect($this->application->fresh()->status)->toBe(JobApplicationStatus::New)
            ->and($this->job->fresh()->status)->toBe(JobPostingStatus::Published);
    });

    test('lists show only the school\'s own vacancies and applicants', function () {
        $theirs = publishedVacancy($this->otherSchool, ['title' => 'Hilltop Chemistry Teacher']);
        JobApplication::factory()->create(['job_posting_id' => $theirs->id, 'full_name' => 'Hilltop Applicant']);

        $this->actingAs($this->admin)->get(route('careers.index'))->assertDontSee('Hilltop Chemistry Teacher');
        $this->actingAs($this->admin)->get(route('careers.applications.index'))->assertDontSee('Hilltop Applicant');

        // Filtering by another school's vacancy shows nothing of theirs.
        $this->actingAs($this->admin)->get(route('careers.applications.index', ['job' => $theirs->uuid]))->assertDontSee('Hilltop Applicant');
    });

    test('a vacancy token only opens on its own school\'s portal', function () {
        $this->get(route('public.jobs.show', ['school' => $this->otherSchool, 'token' => $this->job->public_token]))->assertNotFound();
        $this->post(route('public.jobs.apply', ['school' => $this->otherSchool, 'token' => $this->job->public_token]), applicationFields())->assertNotFound();
    });

    test('applicants cannot reach the dashboard, and a CV has no public address', function () {
        $this->get(route('careers.applications.cv', $this->application))->assertRedirect();
        $this->get(route('careers.index'))->assertRedirect();

        $this->get('/files/'.$this->application->cv_path)->assertNotFound();
        // Laravel's own private-file route refuses an unsigned request.
        expect($this->get('/storage/'.$this->application->cv_path)->status())->toBeIn([403, 404]);
    });
});

describe('on the school\'s own address', function () {
    test('the portal and each vacancy resolve on the school\'s subdomain, and only that school\'s', function () {
        config(['custom_domain.tenant_base_domain' => 'akademicnest.test']);
        // Subdomains are generated from the school name.
        $host = $this->school->fresh()->subdomain.'.akademicnest.test';

        $job = publishedVacancy($this->school->fresh());
        $theirs = publishedVacancy($this->otherSchool->fresh(), ['title' => 'Hilltop Role']);

        expect($job->publicUrl())->toBe("http://{$host}/jobs/".$job->public_token);

        $this->get("http://{$host}/jobs")->assertOk()->assertSee('Mathematics Teacher')->assertDontSee('Hilltop Role');
        $this->get("http://{$host}/jobs/".$job->public_token)->assertOk()->assertSee('Greenfield College');
        $this->get("http://{$host}/jobs/".$theirs->public_token)->assertNotFound();
    });
});
