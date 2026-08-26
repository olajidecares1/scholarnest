<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\FeePaymentMethod;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $structures = $school->feeStructures()->withCount('invoices')->orderByDesc('created_at')->get();

        return view('school-admin.finance.index', [
            'structures' => $structures,
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
        ]);
    }

    public function storeStructure(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'session' => ['required', 'string', 'max:20'],
            'term' => ['nullable', 'string', 'max:50'],
        ]);

        $structure = $school->feeStructures()->create($validated);

        return back()->with('status', "{$structure->name} was created.");
    }

    public function destroyStructure(FeeStructure $structure): RedirectResponse
    {
        $this->authorizeStructure($structure);

        if ($structure->invoices()->exists()) {
            return back()->with('status', "{$structure->name} has invoices already generated and cannot be removed.");
        }

        $name = $structure->name;
        $structure->delete();

        return back()->with('status', "{$name} was removed.");
    }

    public function generateInvoices(FeeStructure $structure): RedirectResponse
    {
        $this->authorizeStructure($structure);

        $students = Student::where('school_id', $structure->school_id)
            ->where('is_active', true)
            ->when($structure->class_name, fn ($query) => $query->where('class_name', $structure->class_name))
            ->whereDoesntHave('invoices', fn ($query) => $query->where('fee_structure_id', $structure->id))
            ->get();

        foreach ($students as $student) {
            Invoice::create([
                'school_id' => $structure->school_id,
                'student_id' => $student->id,
                'fee_structure_id' => $structure->id,
                'title' => $structure->name,
                'amount' => $structure->amount,
            ]);
        }

        return redirect()->route('finance.invoices.index')->with('status', "Generated {$students->count()} invoice(s) for {$structure->name}.");
    }

    public function invoices(Request $request): View
    {
        $school = $request->user()->school;

        $filtered = $school->invoices()
            ->with(['student', 'payments'])
            ->when($request->filled('class'), fn ($query) => $query->whereHas('student', fn ($q) => $q->where('class_name', $request->string('class'))))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $statusFilter = $request->string('status')->toString();
        if ($statusFilter) {
            $filtered = $filtered->filter(fn ($invoice) => $invoice->status() === ucfirst($statusFilter))->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $invoices = new LengthAwarePaginator(
            $filtered->slice(($page - 1) * $perPage, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('school-admin.finance.invoices', [
            'invoices' => $invoices,
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
            'students' => $school->students()->where('is_active', true)->orderBy('last_name')->get(),
            'methodOptions' => FeePaymentMethod::cases(),
            'totalOutstanding' => $school->invoices()->with('payments')->get()->sum(fn ($invoice) => max($invoice->balance(), 0)),
        ]);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = Student::where('school_id', $school->id)->find($validated['student_id']);

        if (! $student) {
            return back()->with('status', 'Could not find that student.');
        }

        $school->invoices()->create([
            'student_id' => $student->id,
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', "Invoice created for {$student->fullName()}.");
    }

    public function destroyInvoice(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        if ($invoice->payments()->exists()) {
            return back()->with('status', 'This invoice has payments recorded and cannot be deleted.');
        }

        $invoice->delete();

        return back()->with('status', 'Invoice was deleted.');
    }

    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($invoice->balance(), 0.01)],
            'paid_at' => ['required', 'date'],
            'method' => ['required', Rule::enum(FeePaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $invoice->payments()->create([
            ...$validated,
            'recorded_by' => auth()->id(),
        ]);

        return back()->with('status', 'Payment recorded.');
    }

    private function authorizeStructure(FeeStructure $structure): void
    {
        $this->authorizeSchoolOwnership($structure);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        $this->authorizeSchoolOwnership($invoice);
    }
}
