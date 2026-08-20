<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $books = Book::where('school_id', $student->school_id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('author', 'like', "%{$search}%"));
            })
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $myLoans = $student->bookLoans()->with('book')->orderByDesc('borrowed_at')->take(10)->get();

        return view('student.library.index', [
            'school' => $school,
            'student' => $student,
            'books' => $books,
            'myLoans' => $myLoans,
        ]);
    }
}
