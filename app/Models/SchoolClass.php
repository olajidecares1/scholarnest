<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_level_id',
        'name',
        'sort_order',
    ];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }
}
