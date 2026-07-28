<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\BookLoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookLoan extends Model
{
    /** @use HasFactory<BookLoanFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'book_id',
        'student_id',
        'borrowed_at',
        'due_at',
        'returned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'date:Y-m-d',
            'due_at' => 'date:Y-m-d',
            'returned_at' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isReturned() && $this->due_at->isPast();
    }

    public function status(): string
    {
        return match (true) {
            $this->isReturned() => 'Returned',
            $this->isOverdue() => 'Overdue',
            default => 'Borrowed',
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status()) {
            'Returned' => 'bg-green-100 text-green-700',
            'Overdue' => 'bg-red-100 text-red-700',
            default => 'bg-blue-100 text-blue-700',
        };
    }
}
