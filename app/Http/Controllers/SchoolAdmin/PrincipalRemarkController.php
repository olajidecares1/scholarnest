<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\PrincipalRemark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Principal's library of reusable remarks.
 *
 * SCOPING IS THE WHOLE SECURITY STORY HERE. The school is always
 * $request->user()->school - taken from the session, never from the request -
 * and every read and write is scoped to it. The route model binding resolves
 * a remark by uuid, and authorizeSchoolOwnership() then refuses one belonging
 * to anybody else, so a Principal who guesses another school's uuid is turned
 * away rather than handed that school's remark.
 *
 * There is no limit on how many a school keeps.
 */
class PrincipalRemarkController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        return view('school-admin.results.remark-library', [
            'remarks' => $this->library($request),
        ]);
    }

    /**
     * The library as JSON, for the picker inside the result preview.
     */
    public function list(Request $request): JsonResponse
    {
        return response()->json([
            'remarks' => $this->library($request)
                ->map(fn (PrincipalRemark $remark) => [
                    'uuid' => $remark->uuid,
                    'body' => $remark->body,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $remark = PrincipalRemark::saveFor($request->user()->school, $validated['body'], $request->user());

        if ($request->wantsJson()) {
            return response()->json(['remark' => ['uuid' => $remark->uuid, 'body' => $remark->body]]);
        }

        return back()->with('status', 'Remark saved to your library.');
    }

    public function update(Request $request, PrincipalRemark $principalRemark): RedirectResponse
    {
        $this->authorizeSchoolOwnership($principalRemark);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $principalRemark->reword($validated['body']);

        return back()->with('status', 'Remark updated.');
    }

    public function destroy(Request $request, PrincipalRemark $principalRemark): RedirectResponse
    {
        $this->authorizeSchoolOwnership($principalRemark);

        $principalRemark->delete();

        // Deliberately says what did NOT happen. Deleting a saved sentence is
        // tidying a list, not unsaying it on a card somebody already has.
        return back()->with('status', 'Remark removed from your library. Results already carrying it are unchanged.');
    }

    /**
     * @return Collection<int, PrincipalRemark>
     */
    private function library(Request $request)
    {
        return PrincipalRemark::query()
            ->where('school_id', $request->user()->school_id)
            ->latest('updated_at')
            ->get();
    }
}
