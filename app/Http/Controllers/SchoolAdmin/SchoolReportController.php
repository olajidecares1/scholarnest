<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Models\ExaminationScore;
use App\Models\Invoice;
use App\Models\School;
use App\Services\Attendance\TermAttendance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolReportController extends Controller
{
    public function summary(Request $request): View
    {
        $school = $request->user()->school;

        $invoices = $school->invoices()->with('payments')->get();
        $scores = ExaminationScore::whereHas('subject.examination', fn ($query) => $query->where('school_id', $school->id))->with('subject')->get();

        return view('school-admin.reports.summary', [
            'school' => $school,
            'generatedAt' => now(),
            'totalStudents' => $school->students()->count(),
            'activeStudents' => $school->students()->where('is_active', true)->count(),
            'totalStaff' => $school->staff()->count(),
            'totalClasses' => $school->schoolClasses()->count(),
            'attendanceAverage' => $this->attendanceAverage($school),
            'termAttendance' => $this->termAttendanceAverage($school),
            'totalInvoiced' => (float) $invoices->sum('amount'),
            'totalCollected' => $invoices->sum(fn (Invoice $invoice) => $invoice->amountPaid()),
            'totalOutstanding' => $invoices->sum(fn (Invoice $invoice) => max($invoice->balance(), 0)),
            'examAverage' => $scores->isNotEmpty() ? round($scores->avg(fn ($score) => $score->percentage()), 1) : null,
            'gradedStudentCount' => $scores->pluck('student_id')->unique()->count(),
        ]);
    }

    /**
     * The school-wide average of the current term's manually entered
     * attendance, when the school records attendance that way.
     *
     * @return ?array{label: string, average: ?float, entered: int}
     */
    private function termAttendanceAverage(School $school): ?array
    {
        if (! $school->usesManualAttendance()) {
            return null;
        }

        $attendance = app(TermAttendance::class);
        [$session, $term] = $attendance->resolvePeriod($school, null, null);

        $summaries = $attendance->withTermSchoolDays(
            $school,
            AttendanceSummary::where('school_id', $school->id)->where('session', $session)->where('term', $term)->get(),
        );

        $percents = $summaries->map(fn (AttendanceSummary $summary) => $summary->percent())->filter(fn (?int $p) => $p !== null);

        return [
            'label' => $term->label().' '.$session,
            'average' => $percents->isEmpty() ? null : round($percents->avg(), 1),
            'entered' => $summaries->count(),
        ];
    }

    private function attendanceAverage(School $school): ?float
    {
        $records = $school->attendanceRecords()->whereDate('date', '>=', now()->subDays(30))->get();

        if ($records->isEmpty()) {
            return null;
        }

        return round($records->filter(fn ($record) => $record->status->isPresentForStats())->count() / $records->count() * 100, 1);
    }
}
