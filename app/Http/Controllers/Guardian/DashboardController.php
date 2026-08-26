<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\MemorandumAudience;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolNotice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $guardian = $request->user('guardian');
        $children = $guardian->students;

        abort_if($children->isEmpty(), 403, 'No children are linked to this account yet. Please contact your school.');

        $activeChild = $children->firstWhere('uuid', $request->string('child')->toString()) ?? $children->first();

        $recentNotices = SchoolNotice::where('school_id', $guardian->school_id)
            ->forAudience(MemorandumAudience::Guardians)
            ->where(fn ($query) => $query->whereNull('class_name')->orWhere('class_name', $activeChild->class_name))
            ->latest()
            ->take(4)
            ->get();

        return view('guardian.dashboard', [
            'school' => $school,
            'guardian' => $guardian,
            'children' => $children,
            'activeChild' => $activeChild,
            'recentNotices' => $recentNotices,
        ]);
    }
}
