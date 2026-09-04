<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Notifications\Notification;

/**
 * Two rules, and they are the same rule seen from two sides:
 *
 *   1. Clicking a notification opens the thing it is about and leaves you
 *      inside your own portal. It never abandons the page you were on.
 *   2. What one surface does with its session never decides where another
 *      surface sends a visitor. The public website is public whoever happens
 *      to be signed in.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Isolation School']), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

describe('Test A - a notification opens what it is about', function () {
    test('a notification with a destination goes there', function () {
        $message = ContactMessage::create([
            'school_id' => $this->school->id, 'name' => 'A Parent', 'message' => 'A question about fees.',
        ]);
        $this->admin->notify(new ContactMessageReceivedNotification($message));

        $this->actingAs($this->admin)
            ->get(route('notifications.read', $this->admin->notifications()->firstOrFail()))
            ->assertRedirect(route('inbox.index'));

        expect($this->admin->unreadNotifications()->count())->toBe(0);
    });

    test('a notification WITHOUT one leaves the reader where they were', function () {
        // This is the bug. It used to redirect to a generic dashboard, which
        // for a school with no active subscription bounces again and lands on
        // the sign-in page - "clicking a notification sends me to the
        // registration page".
        $this->admin->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => 'No destination', 'body' => 'Nothing to open.'];
            }
        });

        $where = route('inbox.index');

        $this->actingAs($this->admin)
            ->from($where)
            ->get(route('notifications.read', $this->admin->notifications()->firstOrFail()))
            ->assertRedirect($where);
    });

    test('a notification belonging to somebody else is refused', function () {
        $stranger = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
        $stranger->notify(new ContactMessageReceivedNotification(ContactMessage::create([
            'school_id' => $this->school->id, 'name' => 'A Parent', 'message' => 'Private.',
        ])));

        $this->actingAs($this->admin)
            ->get(route('notifications.read', $stranger->notifications()->firstOrFail()))
            ->assertForbidden();
    });

    test('a notification belonging to a DIFFERENT KIND of account is refused', function () {
        // The check used to compare ids alone. A User with id 7 and a Staff
        // member with id 7 are different people in different tables, and the
        // notifiable_type is the only thing that says so.
        $staff = Staff::factory()->create(['school_id' => $this->school->id]);
        $staff->notify(new ContactMessageReceivedNotification(ContactMessage::create([
            'school_id' => $this->school->id, 'name' => 'A Parent', 'message' => 'Private.',
        ])));

        $notification = $staff->notifications()->firstOrFail();

        // Line the ids up, so only the type can refuse it.
        $notification->forceFill(['notifiable_id' => $this->admin->id])->save();

        $this->actingAs($this->admin)
            ->get(route('notifications.read', $notification))
            ->assertForbidden();
    });

    test('an off-site destination is not followed', function () {
        $this->admin->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => 'Elsewhere', 'body' => '.', 'url' => 'https://example.invalid/steal'];
            }
        });

        $where = route('inbox.index');

        // A url read out of the database is untrusted input. Followed blindly
        // it is an open redirect from inside a signed-in session.
        $this->actingAs($this->admin)
            ->from($where)
            ->get(route('notifications.read', $this->admin->notifications()->firstOrFail()))
            ->assertRedirect($where);
    });
});

describe('Tests C, D, E - one surface never navigates another', function () {
    test('the school website is the school website for a signed-in School Admin', function () {
        $this->actingAs($this->admin)
            ->get(route('public.school-website', $this->school))
            ->assertOk()
            ->assertSee($this->school->name);
    });

    test('and for a signed-in teacher', function () {
        $staff = Staff::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($staff, 'staff')
            ->get(route('public.school-website', $this->school))
            ->assertOk();
    });

    test('and for a signed-in pupil', function () {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($student, 'student')
            ->get(route('public.school-website', $this->school))
            ->assertOk();
    });

    test('and for a signed-in parent', function () {
        $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($guardian, 'guardian')
            ->get(route('public.school-website', $this->school))
            ->assertOk();
    });

    test('and for a visitor with no session at all', function () {
        $this->get(route('public.school-website', $this->school))->assertOk();
    });

    test('signing out of the admin portal leaves the website untouched', function () {
        // Test C, as far as a server can be asked about it: the sequence of
        // sessions a browser goes through must not change what the website
        // URL returns.
        $this->actingAs($this->admin)->get(route('public.school-website', $this->school))->assertOk();

        $this->post(route('logout'));

        $this->get(route('public.school-website', $this->school))->assertOk();

        $this->actingAs($this->admin)->get(route('public.school-website', $this->school))->assertOk();
    });

    test('every public page of the site stays public while signed in', function () {
        foreach ([
            'public.school-about.index',
            'public.school-news.index',
            'public.school-events.index',
            'public.school-gallery.index',
            'public.school-facilities.index',
            'public.school-admissions.index',
            'public.school-contact.index',
        ] as $routeName) {
            $this->actingAs($this->admin)
                ->get(route($routeName, $this->school))
                ->assertOk();
        }
    });
});
