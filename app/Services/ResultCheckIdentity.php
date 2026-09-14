<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * Which pupil the visitor has identified, between step one and step two.
 *
 * Held in the session and keyed by school, never carried in the URL. A student
 * id in the address would make the whole first step decorative: anyone could
 * skip to the token form for any pupil by editing a number, which is exactly
 * the bypass the flow is supposed to prevent.
 *
 * It is also re-resolved from the database on every read and re-checked against
 * the school, so a session carried from one school's result link cannot be
 * spent on another's.
 */
class ResultCheckIdentity
{
    /**
     * How long an identification stays good for.
     *
     * Short, because it is the weaker half of the pair, it took only an
     * admission number to obtain. Long enough to find the token slip that the
     * school sent home.
     */
    private const TTL_SECONDS = 1800;

    public function remember(Request $request, School $school, Student $student): void
    {
        $request->session()->put($this->key($school), [
            'student_id' => $student->id,
            'at' => now()->timestamp,
        ]);
    }

    /**
     * The identified pupil, or null if there isn't one, it has expired, or it
     * belongs to a different school.
     */
    public function resolve(Request $request, School $school): ?Student
    {
        $stored = $request->session()->get($this->key($school));

        if (! is_array($stored) || ! isset($stored['student_id'], $stored['at'])) {
            return null;
        }

        if (now()->timestamp - (int) $stored['at'] > self::TTL_SECONDS) {
            $this->forget($request, $school);

            return null;
        }

        // Re-read rather than trusted: the pupil may have been deactivated or
        // moved since, and the school clause is what stops a session minted on
        // one school's link from resolving on another's.
        $student = Student::where('id', $stored['student_id'])
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->first();

        if ($student === null) {
            $this->forget($request, $school);
        }

        return $student;
    }

    public function forget(Request $request, School $school): void
    {
        $request->session()->forget($this->key($school));
    }

    private function key(School $school): string
    {
        return "result-check.identified.{$school->id}";
    }
}
