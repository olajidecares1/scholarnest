<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\CbtExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtExam extends Model
{
    /** @use HasFactory<CbtExamFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_exam_body_id',
        'cbt_subject_id',
        'year',
        'instructions',
        'duration_minutes',
        'pass_mark',
        'created_by',
    ];

    /**
     * @return BelongsTo<CbtExamBody, $this>
     */
    public function examBody(): BelongsTo
    {
        return $this->belongsTo(CbtExamBody::class, 'cbt_exam_body_id');
    }

    /**
     * @return BelongsTo<CbtSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(CbtSubject::class, 'cbt_subject_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CbtQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CbtQuestion::class)->orderBy('sort_order');
    }

    public function title(): string
    {
        return "{$this->examBody->code} {$this->subject->name} {$this->year}";
    }
}
