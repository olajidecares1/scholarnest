<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A number a student or staff member used to have, before the school
 * regenerated it. Kept so old receipts, report cards and letters can still be
 * traced to the person, and so the old number still signs them in.
 * See App\Services\IdentifierRegenerator.
 */
class IdentifierChange extends Model
{
    protected $fillable = ['school_id', 'type', 'holder_id', 'old_value', 'new_value', 'batch', 'changed_by'];

    protected function casts(): array
    {
        return ['holder_id' => 'integer'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The id of the student (or staff member) who used to be known by this
     * number at the school, most recent change first, or null.
     */
    public static function holderFor(int $schoolId, string $type, string $oldValue): ?int
    {
        $holder = static::query()
            ->where('school_id', $schoolId)
            ->where('type', $type)
            ->whereRaw('LOWER(old_value) = ?', [mb_strtolower(trim($oldValue))])
            ->latest('id')
            ->value('holder_id');

        return $holder === null ? null : (int) $holder;
    }

    /**
     * Every number this student or staff member had before, most recent
     * first, for their profile page.
     *
     * @return list<string>
     */
    public static function previousFor(Student|Staff $holder): array
    {
        return static::query()
            ->where('school_id', $holder->school_id)
            ->where('type', $holder instanceof Student ? 'admission' : 'staff')
            ->where('holder_id', $holder->getKey())
            ->orderByDesc('id')
            ->pluck('old_value')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Holder ids whose old numbers contain the search term, for list searches.
     *
     * @return list<int>
     */
    public static function holdersMatching(int $schoolId, string $type, string $term): array
    {
        return static::query()
            ->where('school_id', $schoolId)
            ->where('type', $type)
            ->where('old_value', 'like', '%'.$term.'%')
            ->pluck('holder_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
