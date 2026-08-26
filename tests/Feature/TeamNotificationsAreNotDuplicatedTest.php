<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolRegisteredNotification;
use App\Services\TeamNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->team = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'name' => 'EduNest Team',
    ]);
});

/**
 * The registration form, filled in.
 *
 * @return array<string, string>
 */
function registrationPayload(string $schoolName = 'School ABC', string $email = 'admin@schoolabc.test'): array
{
    return [
        'school_name' => $schoolName,
        'email' => $email,
        'phone' => '08011112222',
        'password' => 'Correct-Horse1!',
        'password_confirmation' => 'Correct-Horse1!',
        'terms' => 'on',
    ];
}

// ---------------------------------------------------------------------------
// The reported fault
// ---------------------------------------------------------------------------

test('one school registration produces exactly one notification', function () {
    Notification::fake();

    $this->post(route('register'), registrationPayload())->assertRedirect();

    Notification::assertSentToTimes($this->team, SchoolRegisteredNotification::class, 1);
});

test('one sign-in writes one audit entry, not two', function () {
    // The listener was registered twice - once by Laravel's discovery of
    // app/Listeners and once by hand in AppServiceProvider - so every login
    // wrote its entry twice. A school's User carries the school's name, which
    // is why the EduNest Team saw the same school name appear twice.
    $user = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'name' => 'School ABC',
        'password' => Hash::make('Correct-Horse1!'),
        'school_id' => School::factory()->create()->id,
    ]);

    AuditLog::query()->delete();

    auth()->login($user);

    expect(AuditLog::where('action', 'login')->count())->toBe(1);
});

test('registering announces the school once even though it also signs them in', function () {
    // Registration creates the school AND logs the new admin straight in, so
    // it goes past both notification paths in one request. The whole reported
    // symptom was this producing more than one line about "School ABC".
    AuditLog::query()->delete();

    $this->post(route('register'), registrationPayload())->assertRedirect();

    expect(AuditLog::where('action', 'school.registered')->count())->toBe(1)
        ->and(AuditLog::where('action', 'login')->count())->toBe(1)
        ->and(DB::table('notifications')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// It cannot come back
// ---------------------------------------------------------------------------

test('announcing the same event twice sends once', function () {
    $school = School::factory()->create(['name' => 'School ABC']);
    $notifier = app(TeamNotifier::class);

    $first = $notifier->once(
        SchoolRegisteredNotification::eventKeyFor($school),
        new SchoolRegisteredNotification($school),
    );

    $second = $notifier->once(
        SchoolRegisteredNotification::eventKeyFor($school),
        new SchoolRegisteredNotification($school),
    );

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(DB::table('notifications')->count())->toBe(1);
});

test('a replayed registration request adds no second notification', function () {
    // A refreshed POST, a retried request, a job delivered twice: whatever the
    // cause, the school is the same school and the event key is the same key.
    $school = School::factory()->create(['name' => 'School ABC']);
    $notifier = app(TeamNotifier::class);

    foreach (range(1, 5) as $ignored) {
        $notifier->once(
            SchoolRegisteredNotification::eventKeyFor($school),
            new SchoolRegisteredNotification($school),
        );
    }

    expect(DB::table('notifications')->count())->toBe(1);
});

test('the claim is what prevents it, so a second claim of the key is refused', function () {
    // Proving the guard is the unique index rather than a check-then-send: the
    // row exists before anything is sent, so a racing request loses the insert
    // even if it never sees the first one's notification.
    $school = School::factory()->create();
    $key = SchoolRegisteredNotification::eventKeyFor($school);

    DB::table('notification_events')->insert([
        'event_key' => $key,
        'notification' => SchoolRegisteredNotification::class,
        'created_at' => now(),
    ]);

    $sent = app(TeamNotifier::class)->once($key, new SchoolRegisteredNotification($school));

    expect($sent)->toBeFalse()
        ->and(DB::table('notifications')->count())->toBe(0);
});

test('two different schools each get their own notification', function () {
    // The guard must stop duplicates, not stop the second school registering.
    $this->post(route('register'), registrationPayload('School ABC', 'a@abc.test'))->assertRedirect();

    auth()->logout();

    $this->post(route('register'), registrationPayload('School XYZ', 'b@xyz.test'))->assertRedirect();

    expect(DB::table('notifications')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// What the notification says
// ---------------------------------------------------------------------------

test('the notification carries the registration details the team needs', function () {
    $this->post(route('register'), registrationPayload('School ABC', 'admin@schoolabc.test'))->assertRedirect();

    $data = json_decode(DB::table('notifications')->value('data'), true);
    $school = School::where('name', 'School ABC')->sole();

    expect($data['title'])->toBe('New School Registration')
        ->and($data['school_name'])->toBe('School ABC')
        ->and($data['school_code'])->toBe($school->school_code)
        ->and($data['contact_email'])->toBe('admin@schoolabc.test')
        ->and($data['contact_phone'])->toBe('08011112222')
        ->and($data['registered_at'])->not->toBeNull()
        ->and($data['status'])->toBe('Awaiting subscription')
        ->and($data['event_key'])->toBe('school.registered:'.$school->uuid);
});

test('the team holds one registration notification, and it reaches the dashboard', function () {
    $this->post(route('register'), registrationPayload('School ABC'))->assertRedirect();

    auth()->logout();

    // One RECORD is the thing being asserted. The dashboard shows it in two
    // places - the bell and the activity panel - which is one notification
    // presented twice, not two notifications, and is how every other
    // notification on that page behaves.
    expect($this->team->notifications()->count())->toBe(1);

    $this->actingAs($this->team)
        ->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertSee('New School Registration')
        ->assertSee('School ABC');
});

// ---------------------------------------------------------------------------
// The rest of the workflow
// ---------------------------------------------------------------------------

test('every notification to the team is claimed under an event key', function () {
    // If a new "tell the team" call site is added that sends directly, this
    // fails - which is the point. The rule is meant to hold for the workflow,
    // not just for registration.
    $offenders = [];

    $controllers = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Http/Controllers'))
    );

    foreach ($controllers as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (preg_match('/UserRole::SuperAdmin\)(->each|->get\(\))?[^;]{0,120}->notify\(/s', $source)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

test('the team is called the EduNest Team, not the Super Admin', function () {
    expect(UserRole::SuperAdmin->label())->toBe('EduNest Team');

    $this->actingAs($this->team)
        ->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Super Admin');
});
