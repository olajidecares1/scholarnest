<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'school_id',
        'reporter_name',
        'reporter_email',
        'description',
        'media_path',
        'media_original_name',
        'status',
        'resolution_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Report $report): void {
            $report->reference ??= 'RPT-'.strtoupper(Str::random(8));
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
