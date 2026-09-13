<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A supporting document attached to an application - a certificate, a
 * reference letter. Private, like the CV.
 */
class JobApplicationDocument extends Model
{
    use HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function fileExists(): bool
    {
        return Storage::disk(JobApplication::DISK)->exists($this->path);
    }
}
