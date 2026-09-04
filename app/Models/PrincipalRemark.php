<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\PrincipalRemarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reusable sentence in a school's Principal remark library.
 *
 * Saving the same sentence twice is a no-op rather than a duplicate - see
 * saveFor(), and the unique index behind it.
 */
class PrincipalRemark extends Model
{
    /** @use HasFactory<PrincipalRemarkFactory> */
    use HasFactory, HasUuidRouteKey;

    protected $fillable = [
        'school_id',
        'created_by',
        'body',
        'body_hash',
    ];

    /**
     * Add a remark to a school's library, or return the one already there.
     *
     * The hash is of the NORMALISED text, so "Good work." and "Good  work. "
     * are recognised as the same sentence rather than filling the list with
     * near-identical entries a Principal then has to read carefully to tell
     * apart.
     */
    public static function saveFor(School $school, string $body, ?User $author = null): self
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body));

        return static::firstOrCreate(
            ['school_id' => $school->id, 'body_hash' => hash('sha256', mb_strtolower($body))],
            ['body' => $body, 'created_by' => $author?->id],
        );
    }

    /**
     * Rewrite this remark, keeping its hash in step.
     */
    public function reword(string $body): void
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body));

        $this->update([
            'body' => $body,
            'body_hash' => hash('sha256', mb_strtolower($body)),
        ]);
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
