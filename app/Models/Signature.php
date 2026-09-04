<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Somebody's registered signature.
 *
 * Written by the person it belongs to and by nobody else - see
 * App\Http\Controllers\Concerns\RegistersSignatures, where the owner is always
 * taken from the session and never from the request.
 */
class Signature extends Model
{
    use HasUuidRouteKey;

    protected $fillable = [
        'school_id',
        'path',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The signature as an inline data URI, or null when there is no file.
     *
     * A SIGNATURE HAS NO URL AT ALL NOW, and that is the fix rather than a
     * side effect. It used to be a public file: /storage/staff-signatures/
     * <uuid>.png, no access control, permanent. Anyone who obtained the address
     * could download a principal's signature and reproduce it on anything -
     * which undermines every report card the platform issues, because the
     * signature is the part that makes the document a claim by a named person.
     *
     * A signed URL would have been the natural fix, matching photographs. It is
     * the wrong one here: a signature appears once per document, is a few
     * kilobytes, and is only ever rendered as part of a page the reader is
     * already entitled to see. Embedding it means there is no address to leak,
     * nothing to expire, and no request that could be replayed.
     *
     * It also removes the case that made photographs awkward - a report card
     * opened with a result token has no session, and an embedded image needs no
     * authority of its own.
     */
    public function dataUri(): ?string
    {
        $disk = Storage::disk('local');

        if (! $this->path || ! $disk->exists($this->path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($disk->get($this->path));
    }

    /**
     * Absolute local filesystem path, for dompdf views only.
     *
     * dompdf reads local files within its chroot, so a PDF never needed a URL
     * and is unaffected by there no longer being one.
     */
    public function absolutePath(): ?string
    {
        $disk = Storage::disk('local');

        return $this->path && $disk->exists($this->path)
            ? $disk->path($this->path)
            : null;
    }
}
