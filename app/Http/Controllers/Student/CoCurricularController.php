<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CoCurricularActivity;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoCurricularController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $activities = CoCurricularActivity::where('school_id', $student->school_id)
            ->withCount('students')
            ->orderBy('name')
            ->get();

        $joinedIds = $student->coCurricularActivities()->pluck('co_curricular_activities.id');

        return view('student.co-curricular.index', [
            'school' => $school,
            'activities' => $activities,
            'joinedIds' => $joinedIds,
        ]);
    }

    public function join(Request $request, School $school, CoCurricularActivity $activity): RedirectResponse
    {
        $student = $request->user('student');

        abort_unless($activity->school_id === $student->school_id, 403);

        $student->coCurricularActivities()->syncWithoutDetaching([
            $activity->id => ['joined_at' => now()],
        ]);

        return back()->with('status', "You joined {$activity->name}.");
    }

    public function leave(Request $request, School $school, CoCurricularActivity $activity): RedirectResponse
    {
        $student = $request->user('student');

        abort_unless($activity->school_id === $student->school_id, 403);

        $student->coCurricularActivities()->detach($activity->id);

        return back()->with('status', "You left {$activity->name}.");
    }
}
