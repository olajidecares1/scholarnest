<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Models\School;
use App\Notifications\NewReportNotification;
use App\Services\TeamNotifier;
use App\Support\StoredUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function create(): View
    {
        return view('reports.create', [
            'schools' => School::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $report = Report::create([
            'school_id' => $validated['school_id'] ?? null,
            'reporter_name' => $validated['reporter_name'] ?? null,
            'reporter_email' => $validated['reporter_email'] ?? null,
            'description' => $validated['description'],
        ]);

        if ($request->hasFile('media')) {
            $media = $request->file('media');
            $filename = StoredUpload::name($media);
            $path = "reports/{$report->id}/{$filename}";
            $media->storeAs("reports/{$report->id}", $filename, 'local');

            $report->update([
                'media_path' => $path,
                'media_original_name' => $media->getClientOriginalName(),
            ]);
        }

        // One report, one notification, claimed against the report.
        app(TeamNotifier::class)->once(
            'report.submitted:'.$report->uuid,
            new NewReportNotification($report),
        );

        return redirect()->route('reports.confirmation', ['reference' => $report->reference]);
    }

    public function confirmation(): View
    {
        return view('reports.confirmation', [
            'reference' => request()->query('reference'),
        ]);
    }
}
