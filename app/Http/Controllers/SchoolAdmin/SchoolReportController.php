<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\ExaminationScore;
use App\Models\Invoice;
use App\Models\School;
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
            'totalInvoiced' => (float) $invoices->sum('amount'),
            'totalCollected' => $invoices->sum(fn (Invoice $invoice) => $invoice->amountPaid()),
            'totalOutstanding' => $invoices->sum(fn (Invoice $invoice) => max($invoice->balance(), 0)),
            'examAverage' => $scores->isNotEmpty() ? round($scores->avg(fn ($score) => $score->percentage()), 1) : null,
            'gradedStudentCount' => $scores->pluck('student_id')->unique()->count(),
        ]);
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
