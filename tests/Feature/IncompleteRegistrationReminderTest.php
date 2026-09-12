<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\CompleteYourRegistrationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Chasing a school that started signing up and stopped.
 *
 * Most of what matters here is who does NOT get an email. A reminder that
 * reaches a paying school, or reaches the same school every hour, costs more
 * goodwill than the reminder was ever going to earn.
 */
function abandonedSchool(string $name = 'Half Finished Academy', int $hoursAgo = 48): School
{
    $school = School::factory()->create(['name' => $name]);

    User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $school->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();

    return $school->fresh();
}

test('a school that never chose a plan is reminded', function () {
    Notification::fake();

    $school = abandonedSchool();

    $this->artisan('registrations:remind-incomplete')->assertSuccessful();

    Notification::assertSentTo(
        $school->users->first(),
        CompleteYourRegistrationNotification::class,
    );
});

test('a school that finished is left alone', function () {
    Notification::fake();

    $school = abandonedSchool();

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => Plan::factory()->create(['key' => PlanKey::Basic])->id,
        'status' => SubscriptionStatus::PendingVerification,
    ]);

    $this->artisan('registrations:remind-incomplete')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a rejected payment is not treated as an unfinished registration', function () {
    Notification::fake();

    $school = abandonedSchool();

    // They completed the process; the payment was refused. That is a
    // conversation to have, not grounds for "you never finished signing up".
    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => Plan::factory()->create(['key' => PlanKey::Basic])->id,
        'status' => SubscriptionStatus::Rejected,
    ]);

    $this->artisan('registrations:remind-incomplete')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a school that registered an hour ago is not chased yet', function () {
    Notification::fake();

    abandonedSchool(hoursAgo: 1);

    // The configured wait is 24 hours. Somebody who is still deciding at
    // lunchtime should not be emailed before tea.
    $this->artisan('registrations:remind-incomplete')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a registration older than the window is left alone', function () {
    Notification::fake();

    abandonedSchool(hoursAgo: 24 * 400);

    // Without this floor, the first run after deploying this command emails
    // every school that ever abandoned a signup, however long ago. That is a
    // mass mailing nobody asked for.
    $this->artisan('registrations:remind-incomplete')->assertSuccessful();

    Notification::assertNothingSent();
});

test('one school gets one reminder, however often the command runs', function () {
    Notification::fake();

    $school = abandonedSchool();

    $this->artisan('registrations:remind-incomplete');
    $this->artisan('registrations:remind-incomplete');
    $this->artisan('registrations:remind-incomplete');

    // The command is scheduled hourly. An hourly email is not a reminder,
    // it is harassment.
    Notification::assertSentToTimes(
        $school->users->first(),
        CompleteYourRegistrationNotification::class,
        1,
    );
});

test('a dry run reports without sending or marking anything', function () {
    Notification::fake();

    $school = abandonedSchool();

    $this->artisan('registrations:remind-incomplete', ['--dry-run' => true])
        ->expectsOutputToContain('Half Finished Academy')
        ->assertSuccessful();

    Notification::assertNothingSent();

    expect($school->fresh()->registration_reminder_sent_at)->toBeNull();
});

/**
 * The link exactly as the reminder email carries it - not one built here.
 *
 * These tests used to sign their own URL with URL::temporarySignedRoute(),
 * which signs the ABSOLUTE address. The email signs a RELATIVE one, so the
 * tests passed while every real "Complete My Registration" button answered
 * 403 Invalid signature.
 */
function resumeLinkFromEmail(School $school): string
{
    return (new CompleteYourRegistrationNotification($school))->toArray($school->users->first())['url'];
}

describe('the link in the reminder', function () {
    test('it does not sign anybody in', function () {
        $school = abandonedSchool();

        $url = resumeLinkFromEmail($school);

        // A forwarded email must not be a way into the account. The signature
        // proves the link came from us and nothing about who holds it, so the
        // visitor is sent to sign in like anybody else.
        $response = $this->get($url);

        $response->assertRedirect(route('login', absolute: false));

        $this->assertGuest();

        // The link itself is what they come back to after signing in, so the
        // signature is checked again rather than waved through on the
        // strength of having been valid a moment ago.
        expect(session('url.intended'))->toBe($url);
    });

    test('the school that owns it lands on the step they stopped at', function () {
        $school = abandonedSchool();

        $url = resumeLinkFromEmail($school);

        $this->actingAs($school->users->first())
            ->get($url)
            ->assertRedirect(route('subscriptions.choose-plan', absolute: false));
    });

    test('an unsigned link is refused', function () {
        $school = abandonedSchool();

        $this->get(route('registration.resume', $school))->assertForbidden();
    });

    test('a school that has since finished is sent to its dashboard instead', function () {
        $school = abandonedSchool();

        Subscription::factory()->create([
            'school_id' => $school->id,
            'plan_id' => Plan::factory()->create(['key' => PlanKey::Basic])->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $url = resumeLinkFromEmail($school);

        $this->actingAs($school->users->first())
            ->get($url)
            ->assertRedirect(route('dashboard'));
    });
});
