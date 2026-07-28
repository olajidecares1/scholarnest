<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $books = $school->books()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.library.index', [
            'books' => $books,
            'categories' => $school->books()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'totalTitles' => $school->books()->count(),
            'totalCopies' => (int) $school->books()->sum('copies_total'),
            'onLoanCount' => (int) $school->books()->sum('copies_total') - (int) $school->books()->sum('copies_available'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $book = $school->books()->create([
            ...$validated,
            'copies_available' => $validated['copies_total'],
        ]);

        return back()->with('status', "{$book->title} was added to the library.");
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        $this->authorizeBook($book);

        $validated = $request->validate($this->rules());

        $onLoan = $book->copiesOnLoan();
        $book->update([
            ...$validated,
            'copies_available' => max($validated['copies_total'] - $onLoan, 0),
        ]);

        return back()->with('status', "{$book->title} was updated.");
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorizeBook($book);

        if ($book->copiesOnLoan() > 0) {
            return back()->with('status', "{$book->title} has copies on loan and cannot be removed until they're returned.");
        }

        $title = $book->title;
        $book->delete();

        return back()->with('status', "{$title} was removed from the library.");
    }

    public function loans(Request $request): View
    {
        $school = $request->user()->school;
        $status = $request->string('status', 'active')->toString();

        $loans = BookLoan::query()
            ->whereHas('book', fn ($query) => $query->where('school_id', $school->id))
            ->with(['book', 'student'])
            ->when($status === 'active', fn ($query) => $query->whereNull('returned_at'))
            ->when($status === 'overdue', fn ($query) => $query->whereNull('returned_at')->where('due_at', '<', today()))
            ->when($status === 'returned', fn ($query) => $query->whereNotNull('returned_at'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->whereHas('book', fn ($q) => $q->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('student', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('borrowed_at')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.library.loans', [
            'loans' => $loans,
            'status' => $status,
            'books' => $school->books()->where('copies_available', '>', 0)->orderBy('title')->get(),
            'students' => $school->students()->where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function storeLoan(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'book_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'due_at' => ['required', 'date', 'after:today'],
        ]);

        $book = $school->books()->whereKey($validated['book_id'])->first();
        $student = Student::where('school_id', $school->id)->find($validated['student_id']);

        if (! $book || ! $student) {
            return back()->with('status', 'Could not find that book or student.');
        }

        if ($book->copies_available < 1) {
            return back()->with('status', "{$book->title} has no available copies right now.");
        }

        BookLoan::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'borrowed_at' => today(),
            'due_at' => $validated['due_at'],
        ]);

        $book->decrement('copies_available');

        return back()->with('status', "{$book->title} was issued to {$student->fullName()}.");
    }

    public function returnLoan(BookLoan $loan): RedirectResponse
    {
        $this->authorizeLoan($loan);

        if (! $loan->isReturned()) {
            $loan->update(['returned_at' => today()]);
            $loan->book->increment('copies_available');
        }

        return back()->with('status', "{$loan->book->title} was marked as returned.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:150'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'copies_total' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    private function authorizeBook(Book $book): void
    {
        abort_unless($book->school_id === auth()->user()->school_id, 403);
    }

    private function authorizeLoan(BookLoan $loan): void
    {
        abort_unless($loan->book->school_id === auth()->user()->school_id, 403);
    }
}
