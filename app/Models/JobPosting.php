<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Support\HasUuidRouteKey;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'title',
        'department',
        'employment_type',
        'location',
        'description',
        'is_active',
        'posted_at',
        'closes_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'is_active' => 'boolean',
            'posted_at' => 'date:Y-m-d',
            'closes_at' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }
}
