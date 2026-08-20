<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeeController extends Controller
{
    public function index(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $invoices = $student->invoices()->with('payments')->orderByDesc('due_date')->get();

        $totalOutstanding = $invoices->sum(fn ($invoice) => $invoice->balance());
        $totalPaid = $invoices->sum(fn ($invoice) => $invoice->amountPaid());

        return view('guardian.children.fees', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,
            'invoices' => $invoices,
            'totalOutstanding' => $totalOutstanding,
            'totalPaid' => $totalPaid,
        ]);
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
    }
}
