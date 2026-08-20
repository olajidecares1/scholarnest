<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\CbtExamBodyClassGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtExamBodyClassGrant extends Model
{
    /** @use HasFactory<CbtExamBodyClassGrantFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'cbt_exam_body_id',
        'class_name',
        'granted_by',
    ];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<CbtExamBody, $this>
     */
    public function examBody(): BelongsTo
    {
        return $this->belongsTo(CbtExamBody::class, 'cbt_exam_body_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
