<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\MisconductReport;
use App\Models\School;
use App\Models\User;
use App\Support\SubmissionTopic;
use Illuminate\Http\UploadedFile;

/**
 * The inbox lists what each thing is ABOUT and keeps the body one click away.
 *
 * It used to print every message and every report in full, one under another,
 * so ten long reports made the page unscannable and an administrator looking
 * for one had to read all of them.
 *
 * Nothing is dropped to achieve that. Every word is still stored and still
 * shown, on its own page, where there is room for it.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

describe('the topic a row is listed under', function () {
    test('is the subject when one was given', function () {
        expect(SubmissionTopic::from('Damaged Classroom Projector', 'A long account of what happened.'))
            ->toBe('Damaged Classroom Projector');
    });

    test('is the first sentence when it was not', function () {
        // Both public forms make the subject optional on purpose, a parent
        // asking about fees should not have to summarise first.
        expect(SubmissionTopic::from(null, 'Two pupils were fighting at the bus stop. I saw it from my shop.'))
            ->toBe('Two pupils were fighting at the bus stop');
    });

    test('does not end a sentence on a decimal point or an abbreviation', function () {
        expect(SubmissionTopic::from(null, 'It happened at 3.30pm outside the gate. Nobody stopped.'))
            ->toBe('It happened at 3.30pm outside the gate');
    });

    test('falls back to the opening words when the first sentence is enormous', function () {
        $body = 'This is a single sentence that simply keeps going and going without ever reaching a full stop until it is far too long to be anybody idea of a summary.';

        expect(SubmissionTopic::from(null, $body))
            ->toEndWith('...')
            ->and(mb_strlen(SubmissionTopic::from(null, $body)))->toBeLessThanOrEqual(76);
    });

    test('is never blank', function () {
        // An empty cell in a list of otherwise-titled rows reads as a broken
        // record.
        expect(SubmissionTopic::from(null, null))->toBe('Untitled')
            ->and(SubmissionTopic::from('', ''))->toBe('Untitled');
    });
});

describe('the messages listing', function () {
    beforeEach(function () {
        $this->message = ContactMessage::create([
            'school_id' => $this->school->id,
            'name' => 'A Parent',
            'email' => 'parent@example.com',
            'subject' => 'Admissions for September',
            'message' => 'I would like to know whether there are places left in Year 7 for September.',
        ]);
    });

    test('shows the topic, the sender and the date - not the message', function () {
        $this->actingAs($this->admin)
            ->get(route('inbox.index'))
            ->assertOk()
            ->assertSee('Admissions for September')
            ->assertSee('A Parent')
            ->assertDontSee('whether there are places left in Year 7');
    });

    test('and the message itself is one click away', function () {
        $this->actingAs($this->admin)
            ->get(route('inbox.message', $this->message))
            ->assertOk()
            ->assertSee('Admissions for September')
            ->assertSee('whether there are places left in Year 7')
            ->assertSee('parent@example.com');
    });

    test('opening it marks it read', function () {
        expect($this->message->isUnread())->toBeTrue();

        $this->actingAs($this->admin)->get(route('inbox.message', $this->message))->assertOk();

        $this->message->refresh();

        expect($this->message->isUnread())->toBeFalse()
            ->and($this->message->read_by)->toBe($this->admin->id);
    });

    test('a new one is marked New in the listing', function () {
        $this->actingAs($this->admin)
            ->get(route('inbox.index'))
            ->assertOk()
            ->assertSee('New');
    });

    test('another school cannot open it, even knowing the uuid', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

        $this->actingAs($stranger)
            ->get(route('inbox.message', $this->message))
            ->assertForbidden();
    });

    test('and reading it never marks somebody else\'s as read', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

        $this->actingAs($stranger)->get(route('inbox.message', $this->message))->assertForbidden();

        expect($this->message->fresh()->isUnread())->toBeTrue();
    });
});

describe('the reports listing', function () {
    beforeEach(function () {
        $this->report = MisconductReport::create([
            'school_id' => $this->school->id,
            'reporter_name' => 'A Shopkeeper',
            'subject' => 'Damaged Classroom Projector',
            'description' => 'A very long account of exactly what was broken and how it came to be broken.',
            'status' => MisconductReport::STATUS_NEW,
        ]);
    });

    test('shows the topic, not the account of what happened', function () {
        $this->actingAs($this->admin)
            ->get(route('inbox.index', ['tab' => 'reports']))
            ->assertOk()
            ->assertSee('Damaged Classroom Projector')
            ->assertSee('A Shopkeeper')
            ->assertDontSee('exactly what was broken');
    });

    test('and the whole report is one click away', function () {
        $this->actingAs($this->admin)
            ->get(route('inbox.report', $this->report))
            ->assertOk()
            ->assertSee('exactly what was broken');
    });

    test('opening it does NOT mark it reviewed', function () {
        // Reading is not deciding. "Reviewed" means an administrator did
        // something about it, and the form on the page is what records that.
        $this->actingAs($this->admin)->get(route('inbox.report', $this->report))->assertOk();

        expect($this->report->fresh()->isNew())->toBeTrue();
    });

    test('another school cannot open it', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

        $this->actingAs($stranger)
            ->get(route('inbox.report', $this->report))
            ->assertForbidden();
    });
});

describe('nothing is stored differently', function () {
    test('the full body is kept, whatever the listing shows', function () {
        $body = str_repeat('Every word of this must survive. ', 20);

        $this->post(route('public.contact-message.store', $this->school), [
            'name' => 'A Parent',
            'subject' => 'A short topic',
            'message' => $body,
        ]);

        $message = ContactMessage::firstOrFail();

        expect($message->message)->toBe(trim($body))
            ->and($message->subject)->toBe('A short topic')
            ->and($message->school_id)->toBe($this->school->id);
    });

    test('a reporter may name their report, and may leave it blank', function () {
        $this->post(route('public.misconduct-report.store', $this->school), [
            'reporter_name' => 'A Neighbour',
            'subject' => 'Fighting at the bus stop',
            'description' => 'Two pupils in uniform were fighting outside my shop this morning.',
            'attachments' => [UploadedFile::fake()->image('scene.jpg')],
        ]);

        expect(MisconductReport::firstOrFail()->subject)->toBe('Fighting at the bus stop');

        $this->post(route('public.misconduct-report.store', $this->school), [
            'reporter_name' => 'Another Neighbour',
            'description' => 'A pupil was smoking by the gate. It happens most afternoons.',
            'attachments' => [UploadedFile::fake()->image('scene.jpg')],
        ]);

        $second = MisconductReport::latest('id')->firstOrFail();

        expect($second->subject)->toBeNull()
            // Still listed under something an administrator can act on.
            ->and($second->topic())->toBe('A pupil was smoking by the gate');
    });
});
