<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\MemorandumAudience;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolNotice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $guardian = $request->user('guardian');
        $classNames = $guardian->students->pluck('class_name')->filter()->unique();

        $notices = SchoolNotice::where('school_id', $guardian->school_id)
            ->forAudience(MemorandumAudience::Guardians)
            ->where(fn ($query) => $query->whereNull('class_name')->orWhereIn('class_name', $classNames))
            ->latest()
            ->paginate(10);

        return view('guardian.messages.index', [
            'school' => $school,
            'notices' => $notices,
        ]);
    }
}
