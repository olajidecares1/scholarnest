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
        'instructions',
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

    /**
     * Questions that a person still has to look at before students see them.
     *
     * Extraction flags these: an answer key that named an option the question
     * did not have, options that came back malformed, a diagram that has to be
     * attached by hand.
     *
     * @return HasMany<CbtTestQuestion, $this>
     */
    public function questionsNeedingReview(): HasMany
    {
        return $this->questions()->where('needs_review', true);
    }

    /**
     * Questions with no correct option stored.
     *
     * Separate from needs_review because this is the one that silently corrupts
     * results: a student answering such a question is marked wrong whatever
     * they choose, and neither they nor the teacher is told why.
     *
     * @return HasMany<CbtTestQuestion, $this>
     */
    public function questionsWithoutAnswer(): HasMany
    {
        return $this->questions()->whereDoesntHave('options', fn ($query) => $query->where('is_correct', true));
    }

    /**
     * Why this test cannot go to students yet, or null when it can.
     *
     * Publishing is the moment a test stops being the teacher's draft and
     * starts producing marks on students' records, so it is the right place to
     * insist the questions are answerable. The alternative - letting it through
     * and discovering afterwards that a question could never be answered
     * correctly - means unpicking results that have already been recorded.
     */
    public function publishBlocker(): ?string
    {
        if ($this->questions()->count() === 0) {
            return 'Add at least one question before publishing.';
        }

        $unanswerable = $this->questionsWithoutAnswer()->count();

        if ($unanswerable > 0) {
            return sprintf(
                '%d question(s) have no correct answer marked, so students would be marked wrong no matter what they '
                    .'choose. Set the correct answer on each before publishing.',
                $unanswerable,
            );
        }

        $needingReview = $this->questionsNeedingReview()->count();

        if ($needingReview > 0) {
            return sprintf(
                '%d question(s) still need review. Check each one, then mark it reviewed before publishing.',
                $needingReview,
            );
        }

        return null;
    }

    public function canBePublished(): bool
    {
        return $this->publishBlocker() === null;
    }
}
