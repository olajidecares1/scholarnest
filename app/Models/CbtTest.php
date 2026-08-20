<?php

namespace App\Models;

use App\Enums\CbtTestStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\CbtTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtTest extends Model
{
    /** @use HasFactory<CbtTestFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'staff_id',
        'title',
        'subject',
        'class_name',
        'session',
        'duration_minutes',
        'pass_mark',
        'status',
        'available_from',
        'available_until',
        'shuffle_questions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CbtTestStatus::class,
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'shuffle_questions' => 'boolean',
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
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<CbtTestQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CbtTestQuestion::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<CbtTestDocumentUpload, $this>
     */
    public function documentUploads(): HasMany
    {
        return $this->hasMany(CbtTestDocumentUpload::class);
    }

    /**
     * @return HasMany<CbtTestAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CbtTestAttempt::class);
    }

    /**
     * A test is only visible/startable by students once the teacher has
     * explicitly published it (Draft and Locked are never visible to
     * students) and while it's within its availability window.
     */
    public function isOpenForStudents(): bool
    {
        if ($this->status !== CbtTestStatus::Published) {
            return false;
        }

        if ($this->available_from && $this->available_from->isFuture()) {
            return false;
        }

        if ($this->available_until && $this->available_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Once a student has started an attempt, the test can no longer be
     * hidden again (unpublished back to Locked/Draft) - only forward to
     * Archived. Used to enforce "lock/unlock at any time before students
     * begin the examination."
     */
    public function hasStudentAttempts(): bool
    {
        return $this->attempts()->exists();
    }
}
