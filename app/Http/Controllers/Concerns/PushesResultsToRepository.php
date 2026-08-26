<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\Student;
use App\Services\IncompleteResultException;
use App\Services\ResultRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The "Push to Repository" button, for whoever pressed it.
 *
 * A Class Teacher and a School Admin both publish results, from two different
 * pages on two different guards. What they are doing is the same thing, so it
 * is written once: the same completeness check, the same message when it is
 * refused, the same audit entry.
 *
 * Each controller still says WHO may press it - a teacher only for their own
 * class, an admin for any class in their school. That is the part that
 * genuinely differs, and it stays where it can be read next to the route.
 */
trait PushesResultsToRepository
{
    /**
     * Publish one pupil's card.
     */
    protected function pushResultToRepository(
        Request $request,
        Examination $examination,
        Student $student,
        Model $actor,
        string $actorName,
    ): JsonResponse|RedirectResponse {
        try {
            $record = app(ResultRepository::class)->publish($examination, $student, $actor, $actorName);
        } catch (IncompleteResultException $e) {
            return $this->refuse($request, $e->blockers);
        }

        AuditLog::record(
            'result.pushed_to_repository',
            "Pushed {$student->fullName()}'s {$examination->term->label()} result to the Result Repository.",
            $record,
            $actorName,
        );

        $message = $record->version > 1
            ? "{$student->fullName()}'s result was updated in the Result Repository."
            : "{$student->fullName()}'s result was pushed to the Result Repository.";

        return $this->done($request, $message, [
            'version' => $record->version,
            'pushed_at' => $record->pushed_at->format('M j, Y g:ia'),
        ]);
    }

    /**
     * Publish a whole class, and say plainly who was left behind.
     */
    protected function pushClassToRepository(
        Request $request,
        Examination $examination,
        Model $actor,
        string $actorName,
    ): JsonResponse|RedirectResponse {
        $outcome = app(ResultRepository::class)->publishClass($examination, $actor, $actorName);

        AuditLog::record(
            'result.class_pushed_to_repository',
            "Pushed {$outcome['published']} {$examination->class_name} result(s) to the Result Repository.",
            $examination,
            $actorName,
        );

        $message = match (true) {
            $outcome['published'] === 0 => 'No results were ready to publish.',
            $outcome['skipped'] === [] => "{$outcome['published']} result(s) pushed to the Result Repository.",
            default => "{$outcome['published']} result(s) pushed. "
                .count($outcome['skipped']).' were not ready and were left out.',
        };

        return $this->done($request, $message, [
            'published' => $outcome['published'],

            // Named, with their own reason each. A count of what was skipped
            // is a number the school then has to go hunting through a register
            // to act on.
            'skipped' => $outcome['skipped'],
        ]);
    }

    /**
     * @param  list<string>  $blockers
     */
    private function refuse(Request $request, array $blockers): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'This result is not ready to publish.',
                'blockers' => $blockers,
            ], 422);
        }

        return back()->withErrors(['repository' => implode(' ', $blockers)]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function done(Request $request, string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['status' => $message, ...$extra]);
        }

        return back()->with('status', $message);
    }
}
