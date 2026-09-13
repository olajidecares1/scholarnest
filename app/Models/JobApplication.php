<?php

namespace App\Models;

use App\Enums\JobApplicationStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Somebody's application for a vacancy.
 *
 * Carries its own school_id, not only the vacancy's, so every query and every
 * authorization check in the dashboard scopes by school directly rather than
 * trusting a join. A School Admin reaches an application only through
 * AuthorizesSchoolOwnership on this row.
 */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory, HasUuidRouteKey;

    /** Private: CVs and supporting documents are never public files. */
    public const DISK = 'local';

    public const DIRECTORY = 'job-applications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'job_posting_id',
        'full_name',
        'email',
        'phone',
        'address',
        'cover_letter',
        'qualifications',
        'years_of_experience',
        'cv_path',
        'cv_original_name',
        'cv_mime_type',
        'cv_size_bytes',
        'answers',
        'status',
        'status_changed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobApplicationStatus::class,
            'answers' => 'array',
            'years_of_experience' => 'integer',
            'cv_size_bytes' => 'integer',
            'status_changed_at' => 'datetime',
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
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * @return HasMany<JobApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(JobApplicationDocument::class);
    }

    /**
     * @return HasMany<JobInterview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(JobInterview::class)->orderByDesc('scheduled_for');
    }

    /**
     * @return HasMany<JobApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(JobApplicationEvent::class)->orderByDesc('id');
    }

    /**
     * Move to a new status and record who did it. A move to the status it is
     * already in changes nothing and records nothing.
     */
    public function moveTo(JobApplicationStatus $status, ?User $by = null, ?string $note = null): void
    {
        if ($this->status === $status) {
            return;
        }

        $from = $this->status;

        $this->update(['status' => $status, 'status_changed_at' => now()]);

        $this->events()->create([
            'from_status' => $from?->value,
            'to_status' => $status->value,
            'note' => $note,
            'user_id' => $by?->id,
            'created_at' => now(),
        ]);
    }

    public function cvExists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->cv_path);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->full_name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
