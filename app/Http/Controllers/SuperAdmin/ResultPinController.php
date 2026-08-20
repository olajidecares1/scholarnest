<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ResultCheckingPin;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResultPinController extends Controller
{
    public function index(Request $request): View
    {
        $query = School::query()
            ->whereHas('activeSubscription.plan', fn ($q) => $q->where('key', PlanKey::Basic))
            ->withCount([
                'resultCheckingPins',
                'resultCheckingPins as unassigned_pins_count' => fn ($q) => $q->whereNull('examination_id'),
                'resultCheckingPins as active_pins_count' => fn ($q) => $q->where('status', ResultCheckingPinStatus::Active),
            ]);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $schools = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('super-admin.result-pins.index', [
            'schools' => $schools,
        ]);
    }

    public function show(Request $request, School $school): View
    {
        abort_unless($school->hasPlanAccess(PlanKey::Basic), 404);

        $pins = $school->resultCheckingPins()
            ->with(['examination', 'boundStudent'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.result-pins.show', [
            'school' => $school,
            'pins' => $pins,
            'statuses' => ResultCheckingPinStatus::cases(),
            'generatedCodes' => session('generated_codes'),
        ]);
    }

    public function generate(Request $request, School $school): RedirectResponse
    {
        abort_unless($school->hasPlanAccess(PlanKey::Basic), 403, 'Result PINs can only be generated for schools on the Basic plan.');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $codes = DB::transaction(function () use ($school, $validated, $request) {
            $codes = [];

            for ($i = 0; $i < $validated['quantity']; $i++) {
                $pin = $school->resultCheckingPins()->create([
                    'code' => ResultCheckingPin::generateUniqueCode(),
                    'status' => ResultCheckingPinStatus::Active,
                    'generated_by' => $request->user()->id,
                ]);

                $codes[] = $pin->code;
            }

            return $codes;
        });

        AuditLog::record('result-pin.generated', 'Generated '.count($codes)." result-checking PIN(s) for {$school->name}.", $school);

        return back()
            ->with('status', count($codes)." result-checking PIN(s) generated for {$school->name}.")
            ->with('generated_codes', $codes);
    }

    public function revoke(ResultCheckingPin $pin): RedirectResponse
    {
        $pin->update(['status' => ResultCheckingPinStatus::Revoked]);

        AuditLog::record('result-pin.revoked', "Revoked result-checking PIN {$pin->code} for {$pin->school->name}.", $pin);

        return back()->with('status', "PIN {$pin->code} was revoked.");
    }
}
