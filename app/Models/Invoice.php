<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'fee_structure_id',
        'title',
        'amount',
        'due_date',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    /**
     * @return HasMany<FeePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function amountPaid(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->amount - $this->amountPaid(), 2);
    }

    public function status(): string
    {
        return match (true) {
            $this->balance() <= 0 => 'Paid',
            $this->amountPaid() > 0 => 'Partial',
            default => 'Unpaid',
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status()) {
            'Paid' => 'bg-green-100 text-green-700',
            'Partial' => 'bg-amber-100 text-amber-700',
            default => 'bg-red-100 text-red-700',
        };
    }
}
