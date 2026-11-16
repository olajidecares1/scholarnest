<?php

namespace App\Models;

use App\Enums\ClassStream;
use App\Support\HasUuidRouteKey;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'stream',
        'sort_order',

        // Columns the school added to this class beyond the standard
        // student details. See App\Services\ClassRoster.
        'extra_columns',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stream' => ClassStream::class,
            'extra_columns' => 'array',
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
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    /**
     * @return HasMany<ClassUpdateBatch, $this>
     */
    public function updateBatches(): HasMany
    {
        return $this->hasMany(ClassUpdateBatch::class);
    }
}
