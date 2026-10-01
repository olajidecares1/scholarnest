<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\OfflineScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Using the portals with no connection: the server's half.
 *
 * The service worker keeps only pages the server labels as keepable, and files
 * each under the account it was served to. Writes made offline are sent later,
 * possibly more than once, and must only ever be done once.
 */
function offlineSchool(): School
{
    return activateSchool(School::factory()->create(['name' => 'Greenfield College']), PlanKey::Standard)->fresh();
}

/**
 * A throwaway write route in the web group, counting how often the controller
 * really ran.
 */
function offlineWriteRoute(?string $middleware = null): void
{
    $route = Route::middleware(array_filter(['web', $middleware]))
        ->post('/__offline-test/write', function (Request $request) {
            $request->validate(['title' => 'required|string']);

            $GLOBALS['offlineTestWrites'][] = $request->input('title');

            return redirect('/__offline-test/done')->with('status', 'Saved.');
        });

    $route->name('offline-test.write');

    Route::middleware('web')->get('/__offline-test/done', fn () => 'done');

    // Counted in memory rather than in a table: creating a table here would
    // be DDL, which MySQL commits implicitly, breaking every later test's
    // RefreshDatabase transaction on the MySQL CI job.
    $GLOBALS['offlineTestWrites'] = [];
}

describe('every role\'s pages are labelled for keeping on the device', function () {
    test('a student\'s dashboard is kept, filed under that student', function () {
        $school = offlineSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard', $school))
            ->assertOk()
            ->assertHeader('X-Offline-Cache', 'private')
            ->assertHeader('X-Offline-Scope', OfflineScope::for('student', $student));
    });

    test('a teacher\'s dashboard is kept, filed under that teacher', function () {
        $school = offlineSchool();
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.dashboard', $school))
            ->assertOk()
            ->assertHeader('X-Offline-Cache', 'private')
            ->assertHeader('X-Offline-Scope', OfflineScope::for('staff', $staff));
    });

    test('a parent\'s dashboard is kept, filed under that parent', function () {
        $school = offlineSchool();
        $guardian = Guardian::factory()->create(['school_id' => $school->id, 'is_active' => true]);
        $guardian->students()->attach(Student::factory()->create(['school_id' => $school->id])->id);

        $this->actingAs($guardian, 'guardian')
            ->get(route('guardian.dashboard', $school))
            ->assertOk()
            ->assertHeader('X-Offline-Cache', 'private')
            ->assertHeader('X-Offline-Scope', OfflineScope::for('guardian', $guardian));
    });

    test('a school admin\'s dashboard is kept, filed under that admin', function () {
        $school = offlineSchool();
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('X-Offline-Cache', 'private')
            ->assertHeader('X-Offline-Scope', OfflineScope::for('web', $admin));
    });

    test('two accounts never share a scope, even with the same id on different guards', function () {
        $school = offlineSchool();
        $student = Student::factory()->create(['school_id' => $school->id]);
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        expect(OfflineScope::for('student', $student))->not->toBe(OfflineScope::for('staff', $staff))
            ->and(OfflineScope::for('student', $student))->toHaveLength(32);
    });

    test('the page tells the offline script which account it belongs to', function () {
        $school = offlineSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard', $school))
            ->assertSee('"offlineScope":"'.OfflineScope::for('student', $student).'"', false);
    });

    test('a sign-in page is kept for everybody, but not while somebody is signed in', function () {
        $school = offlineSchool();

        $this->get(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]))
            ->assertHeader('X-Offline-Cache', 'public');
    });

    test('nothing that is not a page is labelled keepable', function () {
        $this->get('/sw.js')->assertHeader('X-Offline-Cache', 'none');
    });
});

describe('a write sent twice is done once', function () {
    test('the same offline key twice creates one record', function () {
        offlineWriteRoute();

        $this->post('/__offline-test/write', ['title' => 'Register 3A', '_offline_key' => 'key-aaaaaaaa-1'])
            ->assertRedirect('/__offline-test/done');

        $this->post('/__offline-test/write', ['title' => 'Register 3A', '_offline_key' => 'key-aaaaaaaa-1'])
            ->assertRedirect('/__offline-test/done');

        expect(count($GLOBALS['offlineTestWrites']))->toBe(1);
    });

    test('a replayed duplicate is answered as done, in JSON', function () {
        offlineWriteRoute();

        $headers = ['X-Offline-Replay' => '1', 'X-Offline-Key' => 'key-bbbbbbbb-2', 'Accept' => 'application/json'];

        $this->post('/__offline-test/write', ['title' => 'Scores'], $headers)
            ->assertOk()
            ->assertJson(['ok' => true, 'redirect' => '/__offline-test/done', 'message' => 'Saved.']);

        $this->post('/__offline-test/write', ['title' => 'Scores'], $headers)
            ->assertOk()
            ->assertJson(['ok' => true, 'duplicate' => true]);

        expect(count($GLOBALS['offlineTestWrites']))->toBe(1);
    });

    test('different keys are different records', function () {
        offlineWriteRoute();

        $this->post('/__offline-test/write', ['title' => 'One', '_offline_key' => 'key-cccccccc-1']);
        $this->post('/__offline-test/write', ['title' => 'Two', '_offline_key' => 'key-cccccccc-2']);

        expect(count($GLOBALS['offlineTestWrites']))->toBe(2);
    });

    test('a write refused by validation leaves the key free to try again', function () {
        offlineWriteRoute();

        $headers = ['X-Offline-Replay' => '1', 'X-Offline-Key' => 'key-dddddddd-1', 'Accept' => 'application/json'];

        $this->post('/__offline-test/write', [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->post('/__offline-test/write', ['title' => 'Fixed'], $headers)
            ->assertOk()
            ->assertJson(['ok' => true]);

        expect(count($GLOBALS['offlineTestWrites']))->toBe(1);
    });

    test('a write that found the session gone is not recorded as done', function () {
        offlineWriteRoute('auth:staff');

        $headers = ['X-Offline-Replay' => '1', 'X-Offline-Key' => 'key-eeeeeeee-1', 'Accept' => 'application/json'];

        $this->post('/__offline-test/write', ['title' => 'Late'], $headers)->assertStatus(401);

        expect(DB::table('offline_sync_receipts')->count())->toBe(0);

        $school = offlineSchool();
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->post('/__offline-test/write', ['title' => 'Late'], $headers)
            ->assertOk()
            ->assertJson(['ok' => true]);

        expect(count($GLOBALS['offlineTestWrites']))->toBe(1);
    });

    test('the key never reaches the controller', function () {
        Route::middleware('web')->post('/__offline-test/echo', fn (Request $request) => response()->json($request->all()));

        $this->postJson('/__offline-test/echo', ['name' => 'x', '_offline_key' => 'key-ffffffff-1'])
            ->assertExactJson(['name' => 'x']);
    });
});

describe('the sync engine', function () {
    test('is told who is signed in and given a fresh token', function () {
        $school = offlineSchool();
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('pwa.session'))
            ->assertOk()
            ->assertJsonStructure(['csrf_token', 'scopes'])
            ->assertJsonPath('scopes', [OfflineScope::for('staff', $staff)]);
    });

    test('a signed-out device is told nobody is signed in', function () {
        $this->getJson(route('pwa.session'))->assertOk()->assertJsonPath('scopes', []);
    });

    test('a teacher who kept working offline is not signed out by the idle timeout when the connection returns', function () {
        $school = offlineSchool();
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->withSession([
                'idle_last_activity_staff' => now()->subMinutes(25),
                'offline_capable_staff' => true,
            ])
            ->withHeaders(['X-Offline-Resume' => '20'])
            ->get(route('staff.dashboard', $school))
            ->assertOk();

        $this->assertAuthenticatedAs($staff, 'staff');
    });

    test('a device nobody touched is still signed out after three idle minutes', function () {
        $school = offlineSchool();
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->withSession([
                'idle_last_activity_staff' => now()->subMinutes(25),
                'offline_capable_staff' => true,
            ])
            ->withHeaders(['X-Offline-Resume' => '600'])
            ->get(route('staff.dashboard', $school))
            ->assertRedirect();

        $this->assertGuest('staff');
    });

    test('the resume header means nothing to a session that never used an offline page', function () {
        $school = offlineSchool();
        $staff = Staff::factory()->create(['school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->withSession(['idle_last_activity_staff' => now()->subMinutes(25)])
            ->withHeaders(['X-Offline-Resume' => '5'])
            ->get(route('staff.dashboard', $school))
            ->assertRedirect();

        $this->assertGuest('staff');
    });
});

describe('the service worker', function () {
    test('carries the offline queue and stores the build output when it installs', function () {
        $script = $this->get('/sw.js')->getContent();

        expect($script)->toContain('AkademicNestOfflineQueue')
            ->and($script)->toContain('const PRECACHE = [')
            ->and($script)->toContain(route('pwa.offline', absolute: false));
    });

    test('keeps only pages the server labelled, filed by account', function () {
        $script = $this->get('/sw.js')->getContent();

        expect($script)->toContain("response.headers.get('X-Offline-Cache')")
            ->and($script)->toContain("response.headers.get('X-Offline-Scope')")
            ->and($script)->toContain('forget-scope');
    });

    test('may fetch the font files the pages link', function () {
        expect($this->get('/sw.js')->headers->get('Content-Security-Policy'))->toContain('https://fonts.bunny.net');
    });
});
