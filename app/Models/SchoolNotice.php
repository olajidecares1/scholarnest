<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\SchoolNoticeFactory;
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
        'class_name',
    ];

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
