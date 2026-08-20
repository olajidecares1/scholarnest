<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ResultCheckingPinStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ResultCheckingPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResultCheckingPinController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $pins = $school->resultCheckingPins()
            ->with(['examination', 'boundStudent'])
            ->when($request->filled('examination_id'), fn ($query) => $query->where('examination_id', $request->integer('examination_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.result-pins.index', [
            'pins' => $pins,
            'examinations' => $school->examinations()->orderByDesc('exam_date')->get(),
        ]);
    }

    public function assign(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $school = $request->user()->school;

        abort_unless($pin->school_id === $school->id, 403);
        abort_if($pin->examination_id !== null, 422, 'This PIN has already been assigned to an examination.');

        $validated = $request->validate([
            'examination_id' => ['required', Rule::exists('examinations', 'id')->where('school_id', $school->id)],
        ]);

        $pin->update(['examination_id' => $validated['examination_id']]);

        AuditLog::record('result-pin.assigned', "Assigned result-checking PIN {$pin->code} to an examination.", $pin);

        return back()->with('status', "PIN {$pin->code} was assigned and is ready to use.");
    }

    public function revoke(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        abort_unless($pin->school_id === $request->user()->school_id, 403);

        $pin->update(['status' => ResultCheckingPinStatus::Revoked]);

        AuditLog::record('result-pin.revoked', "Revoked result-checking PIN {$pin->code}.", $pin);

        return back()->with('status', "PIN {$pin->code} was revoked.");
    }
}
