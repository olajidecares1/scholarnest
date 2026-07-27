<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = Report::query()->with('school');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        return view('super-admin.reports.index', [
            'reports' => $query->latest()->paginate(10)->withQueryString(),
            'stats' => [
                'new' => Report::where('status', ReportStatus::New)->count(),
                'reviewing' => Report::where('status', ReportStatus::Reviewing)->count(),
                'resolved' => Report::where('status', ReportStatus::Resolved)->count(),
            ],
        ]);
    }

    public function show(Report $report): View
    {
        $report->load('school');

        return view('super-admin.reports.show', [
            'report' => $report,
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(ReportStatus::cases(), 'value'))],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update($validated);

        AuditLog::record('report.updated', "Updated report {$report->reference} (status: {$report->status->label()}).", $report);

        return back()->with('status', 'Report updated.');
    }

    public function downloadMedia(Report $report): StreamedResponse
    {
        abort_unless($report->media_path && Storage::disk('local')->exists($report->media_path), 404);

        return Storage::disk('local')->download($report->media_path, $report->media_original_name);
    }
}
