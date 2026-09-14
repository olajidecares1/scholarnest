<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A vacancy a school advertises on its own Job Portal.
 *
 * Two identifiers, on purpose. The uuid addresses it in the School Admin
 * dashboard; the public_token addresses it on the Job Portal and in every link
 * a school shares. Nothing an applicant is ever given can open an admin page,
 * and nothing in the dashboard leaks the share address of a draft.
 */
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory, HasUuidRouteKey;

    public const IMAGE_DIRECTORY = 'job-postings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'title',
        'department',
        'employment_type',
        'status',
        'location',
        'openings',
        'salary_range',
        'description',
        'responsibilities',
        'requirements',
        'qualifications',
        'experience_required',
        'application_instructions',
        'contact_name',
        'contact_email',
        'contact_phone',
        'featured_image_path',
        'posted_at',
        'published_at',
        'closed_at',
        'archived_at',
        'closes_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (JobPosting $job) {
            $job->public_token ??= self::newPublicToken();
            $job->status ??= JobPostingStatus::Draft;
            $job->posted_at ??= today();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => JobPostingStatus::class,
            'openings' => 'integer',
            'posted_at' => 'date:Y-m-d',
            'closes_at' => 'date:Y-m-d',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function newPublicToken(): string
    {
        return Str::random(32);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<JobPostingQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(JobPostingQuestion::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Vacancies currently shown on a Job Portal and taking applications.
     *
     * @param  Builder<JobPosting>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', JobPostingStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhereDate('closes_at', '>=', today()));
    }

    /**
     * Whether the application deadline has passed. The deadline is the whole
     * of that day: a vacancy closing on the 30th still takes applications on
     * the 30th.
     */
    public function deadlinePassed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->endOfDay()->isPast();
    }

    /**
     * Read at the moment an application arrives, never cached: a vacancy
     * closed, archived or past its deadline takes nothing new.
     */
    public function acceptsApplications(): bool
    {
        return $this->status === JobPostingStatus::Published && ! $this->deadlinePassed();
    }

    /**
     * The existing callers' name for "no longer taking applications".
     */
    public function isClosed(): bool
    {
        return ! $this->acceptsApplications();
    }

    /**
     * What an applicant sees in the status line: open, closed, or closed
     * because the deadline has passed.
     */
    public function publicStateLabel(): string
    {
        return match (true) {
            $this->acceptsApplications() => 'Accepting applications',
            $this->status === JobPostingStatus::Published && $this->deadlinePassed() => 'Application deadline has passed',
            default => 'No longer accepting applications',
        };
    }

    /**
     * This vacancy's own address on its school's Job Portal, the school's
     * custom domain or subdomain where it has one, the platform path where
     * not. The link a school shares, and the only way in for an applicant.
     */
    public function publicUrl(): string
    {
        return $this->school->publicUrl('public.jobs.show', ['token' => $this->public_token]);
    }

    public function featuredImageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->featured_image_path);
    }

    /**
     * A short plain-text summary for a link preview.
     */
    public function summary(int $limit = 180): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $this->description))), $limit);
    }
}
