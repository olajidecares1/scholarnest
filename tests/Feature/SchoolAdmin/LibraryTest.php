<?php

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a book', function () {
    $response = $this->actingAs($this->admin)->post(route('library.store'), [
        'title' => 'Things Fall Apart',
        'author' => 'Chinua Achebe',
        'copies_total' => 3,
    ]);

    $response->assertRedirect();

    $book = Book::where('title', 'Things Fall Apart')->firstOrFail();
    expect($book->school_id)->toBe($this->school->id);
    expect($book->copies_available)->toBe(3);
});

test('a school admin only sees books from their own school', function () {
    $otherSchool = School::factory()->create();
    Book::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Book']);
    Book::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Book']);

    $this->actingAs($this->admin)
        ->get(route('library.index'))
        ->assertSee('Own Book')
        ->assertDontSee('Other Book');
});

test('a school admin can issue a book loan', function () {
    $book = Book::factory()->create(['school_id' => $this->school->id, 'copies_total' => 2, 'copies_available' => 2]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('library.loans.store'), [
        'book_id' => $book->id,
        'student_id' => $student->id,
        'due_at' => now()->addWeek()->toDateString(),
    ])->assertRedirect();

    expect($book->fresh()->copies_available)->toBe(1);
    expect(BookLoan::where('book_id', $book->id)->where('student_id', $student->id)->exists())->toBeTrue();
});

test('a book with no available copies cannot be loaned out', function () {
    $book = Book::factory()->create(['school_id' => $this->school->id, 'copies_total' => 1, 'copies_available' => 0]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('library.loans.store'), [
        'book_id' => $book->id,
        'student_id' => $student->id,
        'due_at' => now()->addWeek()->toDateString(),
    ]);

    expect(BookLoan::where('book_id', $book->id)->exists())->toBeFalse();
});

test('returning a loan increments the available copy count', function () {
    $book = Book::factory()->create(['school_id' => $this->school->id, 'copies_total' => 2, 'copies_available' => 1]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $loan = BookLoan::factory()->create(['book_id' => $book->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->post(route('library.loans.return', $loan))
        ->assertRedirect();

    expect($loan->fresh()->returned_at)->not->toBeNull();
    expect($book->fresh()->copies_available)->toBe(2);
});

test('a book with an active loan cannot be deleted', function () {
    $book = Book::factory()->create(['school_id' => $this->school->id, 'copies_total' => 1, 'copies_available' => 0]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    BookLoan::factory()->create(['book_id' => $book->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)->delete(route('library.destroy', $book));

    expect(Book::find($book->id))->not->toBeNull();
});

test('a school admin cannot return a loan from another school\'s book', function () {
    $otherSchool = School::factory()->create();
    $book = Book::factory()->create(['school_id' => $otherSchool->id]);
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);
    $loan = BookLoan::factory()->create(['book_id' => $book->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->post(route('library.loans.return', $loan))
        ->assertForbidden();
});
