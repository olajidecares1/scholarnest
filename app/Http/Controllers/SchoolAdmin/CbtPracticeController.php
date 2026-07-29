<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\CbtExamBody;
use Illuminate\View\View;

class CbtPracticeController extends Controller
{
    public function index(): View
    {
        return view('school-admin.cbt-practice.index', [
            'examBodies' => CbtExamBody::withCount(['subjects', 'exams'])->orderBy('name')->get(),
        ]);
    }

    public function show(CbtExamBody $examBody): View
    {
        return view('school-admin.cbt-practice.show', [
            'examBody' => $examBody,
            'exams' => $examBody->exams()->with('subject')->withCount('questions')->orderByDesc('year')->get(),
        ]);
    }
}
