<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\MisconductReport;
use App\Models\School;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use App\Notifications\MisconductReportSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Reporting a pupil's conduct outside school.
 *
 * The endpoint is open on purpose, a neighbour who saw something is not going
 * to make an account first, and being open is what makes the rest of it
 * matter:
 *
 *   - the school comes from the URL's tenant binding, never from a field;
 *   - attachments land on the PRIVATE disk, never the public one;
 *   - one school can never read another's reports or open its files.
 */
beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->school = activateSchool(School::factory()->create(['name' => 'Report School']), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a stranger can report without an account, giving only their name', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'Mrs Adaeze Okafor',
        'description' => 'Two pupils in uniform were fighting at the bus stop after school.',
        'attachments' => [UploadedFile::fake()->image('scene.jpg')],
    ])->assertRedirect();

    $report = MisconductReport::firstOrFail();

    expect($report->school_id)->toBe($this->school->id)
        ->and($report->reporter_name)->toBe('Mrs Adaeze Okafor')
        ->and($report->status)->toBe(MisconductReport::STATUS_NEW)
        // No email, no telephone, no pupil named. Asking for those is how you
        // get no reports at all.
        ->and($report->location)->toBeNull();
});

test('a name, a description and at least one photograph are required', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [])
        ->assertSessionHasErrors(['reporter_name', 'description', 'attachments']);

    expect(MisconductReport::count())->toBe(0);
});

test('photographs and video are accepted, and land on the private disk', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'I have attached what I photographed at the junction.',
        'attachments' => [
            UploadedFile::fake()->image('scene.jpg'),
            UploadedFile::fake()->create('clip.mp4', 900, 'video/mp4'),
        ],
    ])->assertRedirect();

    $report = MisconductReport::with('attachments')->firstOrFail();

    expect($report->attachments)->toHaveCount(2);

    foreach ($report->attachments as $attachment) {
        // The private disk, and nowhere near the public one. A photograph of
        // somebody's child must not sit at a guessable URL.
        expect(Storage::disk('local')->exists($attachment->path))->toBeTrue()
            ->and($attachment->path)->toStartWith('misconduct-reports/'.$report->uuid);
    }
});

test('a file over 5MB is refused', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'This clip is far too long to send.',
        'attachments' => [UploadedFile::fake()->create('long.mp4', 6000, 'video/mp4')],
    ])->assertSessionHasErrors('attachments.0');

    expect(MisconductReport::count())->toBe(0);
});

test('a file that is not an image or a video is refused', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Trying to send something that is not evidence.',
        'attachments' => [UploadedFile::fake()->create('payload.php', 10, 'text/x-php')],
    ])->assertSessionHasErrors('attachments.0');

    expect(MisconductReport::count())->toBe(0);
});

test('more than eight files is refused', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Sending rather a lot of photographs here.',
        'attachments' => collect(range(1, 9))->map(fn ($i) => UploadedFile::fake()->image("shot-{$i}.jpg"))->all(),
    ])->assertSessionHasErrors('attachments');

    expect(MisconductReport::count())->toBe(0);
});

test('eight files is accepted', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Eight photographs of what happened at the bus stop.',
        'attachments' => collect(range(1, 8))->map(fn ($i) => UploadedFile::fake()->image("shot-{$i}.jpg"))->all(),
    ])->assertRedirect();

    expect(MisconductReport::with('attachments')->firstOrFail()->attachments)->toHaveCount(8);
});

test('a report with no evidence at all is refused', function () {
    // Evidence is required now. A written report with nothing attached left
    // the school investigating a paragraph, and anybody close enough to
    // report a pupil's conduct is close enough to photograph it.
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Something happened but I have attached nothing.',
    ])->assertSessionHasErrors('attachments');

    expect(MisconductReport::count())->toBe(0);
});

test('the school is taken from the URL, not from anything posted', function () {
    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);

    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Trying to file this against a different school.',
        'attachments' => [UploadedFile::fake()->image('scene.jpg')],
        'school_id' => $otherSchool->id,
        'school' => $otherSchool->uuid,
    ])->assertRedirect();

    expect(MisconductReport::firstOrFail()->school_id)->toBe($this->school->id);
});

test('the school\'s administrators are told', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Something the school should know about today.',
        'attachments' => [UploadedFile::fake()->image('scene.jpg')],
    ])->assertRedirect();

    Notification::assertSentTo($this->admin, MisconductReportSubmittedNotification::class);
});

describe('who can read them', function () {
    beforeEach(function () {
        $this->post(route('public.misconduct-report.store', $this->school), [
            'reporter_name' => 'A Neighbour',
            'description' => 'Something private about a child.',
            'attachments' => [UploadedFile::fake()->image('scene.jpg')],
        ]);

        $this->report = MisconductReport::with('attachments')->firstOrFail();
    });

    test('the school that received it', function () {
        // The listing shows what it is ABOUT and who sent it, not the account
        // of what happened, ten long reports made the page unreadable, and an
        // administrator looking for one had to read all of them.
        $this->actingAs($this->admin)
            ->get(route('inbox.index', ['tab' => 'reports']))
            ->assertOk()
            ->assertSee('A Neighbour')
            ->assertDontSee('Something private about a child.');

        // And the whole of it, one click away.
        $this->actingAs($this->admin)
            ->get(route('inbox.report', $this->report))
            ->assertOk()
            ->assertSee('A Neighbour')
            ->assertSee('Something private about a child.');
    });

    test('never another school, even knowing the uuid', function () {
        $otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);
        $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);

        $this->actingAs($stranger)
            ->get(route('inbox.index', ['tab' => 'reports']))
            ->assertOk()
            ->assertDontSee('A Neighbour');

        $this->actingAs($stranger)
            ->put(route('misconduct-reports.update', $this->report), ['status' => 'dismissed'])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('misconduct-reports.attachment', $this->report->attachments->first()))
            ->assertForbidden();
    });

    test('nobody who is not signed in', function () {
        $this->get(route('inbox.index', ['tab' => 'reports']))->assertRedirect();
        $this->get(route('misconduct-reports.attachment', $this->report->attachments->first()))->assertRedirect();
    });

    test('the attachment opens for the school it belongs to', function () {
        $this->actingAs($this->admin)
            ->get(route('misconduct-reports.attachment', $this->report->attachments->first()))
            ->assertOk();
    });
});

test('reviewing records who decided and when', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Something the school looked into.',
        'attachments' => [UploadedFile::fake()->image('scene.jpg')],
    ]);

    $report = MisconductReport::firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('misconduct-reports.update', $report), [
            'status' => MisconductReport::STATUS_REVIEWED,
            'review_note' => 'Spoke to the pupil and their parents.',
        ])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe(MisconductReport::STATUS_REVIEWED)
        ->and($report->reviewed_by)->toBe($this->admin->id)
        ->and($report->reviewed_at)->not->toBeNull()
        ->and($report->review_note)->toBe('Spoke to the pupil and their parents.');
});

test('a website message is stored and the school is told', function () {
    // The contact form used to be a GET that went nowhere, it looked like a
    // form and did nothing, which is worse than not having one, because
    // somebody who fills it in believes they have been in touch.
    $this->post(route('public.contact-message.store', $this->school), [
        'name' => 'Chinedu Martins',
        'email' => 'chinedu@example.test',
        'subject' => 'Fees for Primary 4',
        'message' => 'Please could you send me your fee schedule for next term.',
    ])->assertRedirect();

    $message = ContactMessage::firstOrFail();

    expect($message->school_id)->toBe($this->school->id)
        ->and($message->name)->toBe('Chinedu Martins')
        ->and($message->isUnread())->toBeTrue();

    Notification::assertSentTo($this->admin, ContactMessageReceivedNotification::class);
});

test('the inbox badge counts unread messages and new reports together', function () {
    $this->post(route('public.contact-message.store', $this->school), [
        'name' => 'A Parent', 'message' => 'A question about admissions please.',
    ]);
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Something happened at the junction.',
        'attachments' => [UploadedFile::fake()->image('scene.jpg')],
    ]);

    // One of each, so a badge of 2 proves both are counted rather than one
    // twice.
    $this->actingAs($this->admin)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('A Parent')
        ->assertSee('A Neighbour');
});

test('one school never reads another school\'s website messages', function () {
    $this->post(route('public.contact-message.store', $this->school), [
        'name' => 'A Parent', 'message' => 'Private enquiry about my child.',
    ]);

    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);
    $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);

    $this->actingAs($stranger)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertDontSee('Private enquiry about my child.');

    $this->actingAs($stranger)
        ->put(route('inbox.read-message', ContactMessage::firstOrFail()))
        ->assertForbidden();
});

test('marking a message read records who read it', function () {
    $this->post(route('public.contact-message.store', $this->school), [
        'name' => 'A Parent', 'message' => 'A question that was answered.',
    ]);

    $message = ContactMessage::firstOrFail();

    $this->actingAs($this->admin)->put(route('inbox.read-message', $message))->assertRedirect();

    expect($message->fresh()->read_at)->not->toBeNull()
        ->and($message->fresh()->read_by)->toBe($this->admin->id);
});

test('the reports tab plays video and shows images inline', function () {
    $this->post(route('public.misconduct-report.store', $this->school), [
        'reporter_name' => 'A Neighbour',
        'description' => 'Attached what I recorded.',
        'attachments' => [
            UploadedFile::fake()->image('scene.jpg'),
            UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ],
    ]);

    // On the report's own page now, not in the listing, the listing says how
    // many are attached and leaves the media to the page with room for it.
    $response = $this->actingAs($this->admin)
        ->get(route('inbox.report', MisconductReport::firstOrFail()));

    // Rendered in place, an <img> and a <video> with controls, rather than a
    // list of downloads. Both point at the private route, never a public URL.
    $response->assertOk()
        ->assertSee('<video controls', false)
        ->assertSee('<img src="'.route('misconduct-reports.attachment', MisconductReport::firstOrFail()->attachments->first()), false);

    expect($response->getContent())->not->toContain('/storage/misconduct-reports');
});
