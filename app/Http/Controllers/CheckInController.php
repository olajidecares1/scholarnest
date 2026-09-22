<?php

namespace App\Http\Controllers;

use App\Enums\CheckInOutcome;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Attendance\CheckInService;
use App\Services\Attendance\ScannedPunch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The page behind the poster.
 *
 * ONE ROUTE FOR BOTH PORTALS. There is one poster on the wall and it cannot
 * know who is about to scan it, so this route belongs to neither the student
 * guard nor the staff guard: it reads whichever of the two is signed in on
 * this phone and records against them.
 *
 * THE PAGE CARRIES NO NAME. Not an oversight, a requirement: the service
 * worker is allowed to keep a copy of this one page so it still opens with no
 * signal, and the reasoning in resources/views/pwa/service-worker.blade.php,
 * that a cached page is a page the next person to pick up the phone can read,
 * only stays true while there is nothing personal in it. Who is signed in
 * arrives separately, from `whoami`, which is never cached.
 */
class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkIns) {}

    /**
     * The scan page. Impersonal by design, see the class comment.
     */
    public function show(School $school, string $token): View
    {
        abort_unless($this->tokenMatches($school, $token), 404);

        return view('check-in.scan', [
            'school' => $school,
            'token' => $token,
        ]);
    }

    /**
     * Who is holding this phone, and may they check in here?
     *
     * Its own request so that the page above can stay cacheable. Answers for
     * the signed-out too, because "sign in first" is a useful answer.
     *
     * It also hands out the CSRF token. A page served from the worker's cache
     * may have been baked days and a session ago, so the token that came with
     * the HTML cannot be trusted; this one is fetched at the moment of posting
     * and is therefore always the live one. Sending it here is not a weakening:
     * the response is same-origin-only and a cross-site caller cannot read it.
     */
    public function whoami(School $school, string $token): JsonResponse
    {
        abort_unless($this->tokenMatches($school, $token), 404);

        $person = $this->scanner($school);

        if (! $person) {
            return response()->json([
                'signed_in' => false,
                'csrf_token' => csrf_token(),

                // The school's own portal page, which lists every portal this
                // school has. One link rather than two: the page does not know
                // whether a pupil or a teacher is holding the phone.
                'portal_url' => route('portal.index', ['school' => $school]),
            ]);
        }

        return response()->json([
            'signed_in' => true,
            'csrf_token' => csrf_token(),
            'name' => trim("{$person->first_name} {$person->last_name}"),
            'allowed' => $this->checkIns->isAllowed($school, $person),
            'not_allowed_message' => CheckInOutcome::NotAllowed->message(),
            'radius_metres' => $school->check_in_radius_metres,
        ]);
    }

    /**
     * Record one or more scans.
     *
     * Always a list, even for the scan happening right now, because the page
     * and the service worker send through the same door: one scan online, a
     * pocketful when signal comes back.
     */
    public function store(Request $request, School $school, string $token): JsonResponse
    {
        abort_unless($this->tokenMatches($school, $token), 404);

        $person = $this->scanner($school);

        if (! $person) {
            // 401 and not a redirect: the caller is fetch(), and the queue on
            // the device keeps the scan and tries again after a sign-in rather
            // than throwing it away.
            return response()->json(['message' => 'Sign in to check in.'], 401);
        }

        $validated = $request->validate([
            'scans' => ['required', 'array', 'min:1', 'max:50'],
            'scans.*.client_uuid' => ['required', 'uuid'],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'scans.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'scans.*.accuracy_metres' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'scans.*.was_queued' => ['nullable', 'boolean'],
        ]);

        $results = [];

        foreach ($validated['scans'] as $payload) {
            $scan = $this->checkIns->record($school, $person, ScannedPunch::fromArray($payload));

            $results[] = [
                'client_uuid' => $scan->client_uuid,
                'outcome' => $scan->outcome->value,
                'kind' => $scan->kind->value,
                'recorded' => $scan->outcome->wasRecorded(),
                'message' => $scan->outcome->message(),
                'at' => $scan->scanned_at->setTimezone($school->timezone ?: config('app.timezone'))->format('g:i:sa'),
            ];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Whoever is signed in on this device AT THIS SCHOOL.
     *
     * The school check matters: one phone can hold a session for a school it
     * is not standing outside, and a scan must never be recorded against
     * somebody else's school because they happened to be signed in.
     */
    private function scanner(School $school): Student|Staff|null
    {
        $student = Auth::guard('student')->user();

        if ($student instanceof Student && $student->school_id === $school->id) {
            return $student;
        }

        $staff = Auth::guard('staff')->user();

        if ($staff instanceof Staff && $staff->school_id === $school->id) {
            return $staff;
        }

        return null;
    }

    /**
     * Constant-time comparison: the token is the poster's secret and this
     * route is public, so it should not be discoverable a character at a time.
     */
    private function tokenMatches(School $school, string $token): bool
    {
        return $school->check_in_enabled
            && is_string($school->check_in_token)
            && hash_equals($school->check_in_token, $token);
    }
}
