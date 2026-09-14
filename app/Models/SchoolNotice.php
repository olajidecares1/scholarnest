<?php

namespace App\Models;

use App\Enums\MemorandumAudience;
use App\Support\HasUuidRouteKey;
use Database\Factories\SchoolNoticeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolNotice extends Model
{
    /** @use HasFactory<SchoolNoticeFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'sent_by',
        'title',
        'body',
        'audience',
        'class_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => MemorandumAudience::class,
        ];
    }

    /**
     * Only the memoranda this audience is addressed in.
     *
     * A memorandum carries who it is for; until this existed nothing read that
     * back, so a note to "Teachers & Staff" appeared in front of students and
     * parents anyway, the write side of the feature without the read side.
     *
     * "Everyone" is stored as its own value rather than expanded when saved,
     * so matching means matching either this audience or All.
     *
     * @param  Builder<SchoolNotice>  $query
     * @return Builder<SchoolNotice>
     */
    public function scopeForAudience(Builder $query, MemorandumAudience $audience): Builder
    {
        return $query->whereIn('audience', [$audience->value, MemorandumAudience::All->value]);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * @return HasMany<SchoolNoticeRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(SchoolNoticeRead::class);
    }
}
