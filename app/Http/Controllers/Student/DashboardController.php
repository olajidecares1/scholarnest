<?php

namespace App\Http\Controllers\Student;

use App\Enums\MemorandumAudience;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\SchoolNoticeRead;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $pendingAssignmentsCount = Assignment::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->where('due_date', '>=', today())
            ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $student->id)
                ->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Graded]))
            ->count();

        $noticesQuery = SchoolNotice::where('school_id', $student->school_id)
            ->forAudience(MemorandumAudience::Students)
            ->where(fn ($q) => $q->whereNull('class_name')->orWhere('class_name', $student->class_name));

        $readNoticeIds = SchoolNoticeRead::where('student_id', $student->id)->pluck('school_notice_id');
        $unreadNoticesCount = (clone $noticesQuery)->whereNotIn('id', $readNoticeIds)->count();

        $recentNotices = (clone $noticesQuery)->latest()->take(4)->get();

        return view('student.dashboard', [
            'school' => $school,
            'student' => $student,
            'pendingAssignmentsCount' => $pendingAssignmentsCount,
            'unreadNoticesCount' => $unreadNoticesCount,
            'unreadNotificationsCount' => $student->unreadNotifications()->count(),
            'recentNotices' => $recentNotices,
        ]);
    }
}
