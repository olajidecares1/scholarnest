<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photograph or short video attached to a misconduct report.
 *
 * Stored on the PRIVATE disk. There is deliberately no url() here: the only
 * way to see one of these is through the School Admin route that checks the
 * file belongs to the acting school first. A public URL on a photograph of
 * somebody's child would be the whole problem.
 */
class MisconductReportAttachment extends Model
{
    use HasUuidRouteKey;

    protected $fillable = [
        'misconduct_report_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    /**
     * @return BelongsTo<MisconductReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(MisconductReport::class, 'misconduct_report_id');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->path);
    }

    public function readableSize(): string
    {
        return $this->size_bytes >= 1048576
            ? round($this->size_bytes / 1048576, 1).' MB'
            : max(1, (int) round($this->size_bytes / 1024)).' KB';
    }
}
