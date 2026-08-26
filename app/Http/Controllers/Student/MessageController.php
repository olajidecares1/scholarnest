<?php

namespace App\Http\Controllers\Student;

use App\Enums\MemorandumAudience;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\SchoolNoticeRead;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $notices = SchoolNotice::where('school_id', $student->school_id)
            ->forAudience(MemorandumAudience::Students)
            ->where(fn ($query) => $query->whereNull('class_name')->orWhere('class_name', $student->class_name))
            ->latest()
            ->paginate(10);

        $readIds = SchoolNoticeRead::where('student_id', $student->id)->pluck('school_notice_id');

        return view('student.messages.index', [
            'school' => $school,
            'notices' => $notices,
            'readIds' => $readIds,
        ]);
    }

    public function show(Request $request, School $school, SchoolNotice $notice): View
    {
        $student = $request->user('student');

        abort_unless($notice->school_id === $student->school_id, 403);
        abort_unless($notice->class_name === null || $notice->class_name === $student->class_name, 403);

        // Addressed to students, or to everyone. A memorandum meant for the
        // staff room is refused here as well as hidden from the list, because
        // hiding it from a list is not a rule - the URL is guessable.
        abort_unless(in_array(MemorandumAudience::Students, $notice->audience->groups(), true), 403);

        $notice->reads()->firstOrCreate(
            ['student_id' => $student->id],
            ['read_at' => now()],
        );

        return view('student.messages.show', [
            'school' => $school,
            'notice' => $notice,
        ]);
    }
}
