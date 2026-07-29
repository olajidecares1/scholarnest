<?php

namespace App\Models;

use App\Enums\Gender;
use App\Support\HasUuidRouteKey;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'admission_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'class_name',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'address',
        'phone',
        'email',
        'photo_path',
        'admission_date',
        'is_active',
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
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'is_active' => 'boolean',
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
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<TransportAssignment, $this>
     */
    public function transportAssignment(): HasOne
    {
        return $this->hasOne(TransportAssignment::class);
    }

    /**
     * @return HasOne<HostelAllocation, $this>
     */
    public function hostelAllocation(): HasOne
    {
        return $this->hasOne(HostelAllocation::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
