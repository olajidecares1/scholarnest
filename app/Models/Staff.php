<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Support\HasUuidRouteKey;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'staff_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'role',
        'department',
        'qualification',
        'employment_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address',
        'phone',
        'email',
        'photo_path',
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
            'role' => StaffRole::class,
            'date_of_birth' => 'date',
            'employment_date' => 'date',
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
