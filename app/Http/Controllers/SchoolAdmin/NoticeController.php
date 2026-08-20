<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Notifications\NewNoticePosted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.notices.index', [
            'notices' => $school->notices()->latest()->paginate(10),
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'class_name' => ['nullable', 'string', 'max:50'],
        ]);

        $notice = $school->notices()->create([
            ...$validated,
            'sent_by' => $request->user()->id,
        ]);

        $students = Student::where('school_id', $school->id)
            ->where('is_active', true)
            ->when($notice->class_name, fn ($query) => $query->where('class_name', $notice->class_name))
            ->get();

        $students->each(fn (Student $student) => $student->notify(new NewNoticePosted($notice)));

        return back()->with('status', "Notice sent to {$students->count()} student(s).");
    }
}
