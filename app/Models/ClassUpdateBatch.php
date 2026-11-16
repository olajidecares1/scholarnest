<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bulk update of a class, from an uploaded list or the class editor, and
 * the automatic backup taken as it was applied.
 *
 * The snapshot holds, for every record the update touched, the values before
 * and after, plus the students it created and the columns it added. That is
 * all a rollback needs: put the "before" values back wherever the record still
 * holds the "after" ones, and leave anything changed since alone.
 *
 * @property array{updated: array<int, array{name: string, before: array<string, mixed>, after: array<string, mixed>}>, created: list<int>, removed: array<int, array{name: string, class_name: string|null}>, extra_columns_before: list<string>|null, extra_columns_after: list<string>|null} $snapshot
 */
class ClassUpdateBatch extends Model
{
    use HasUuidRouteKey;

    public const SOURCE_FILE = 'file';

    public const SOURCE_EDITOR = 'editor';

    protected $fillable = [
        'school_id',
        'school_class_id',
        'class_name',
        'user_id',
        'user_name',
        'source',
        'file_name',
        'summary',
        'snapshot',
        'rolled_back_at',
        'rolled_back_by',
        'rollback_notes',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'snapshot' => 'array',
            'rollback_notes' => 'array',
            'rolled_back_at' => 'datetime',
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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRolledBack(): bool
    {
        return $this->rolled_back_at !== null;
    }

    /**
     * "3 updated, 2 added, 1 removed from the class", for the history list.
     */
    public function describe(): string
    {
        $summary = $this->summary ?? [];
        $parts = [];

        if (($summary['updated'] ?? 0) > 0) {
            $parts[] = $summary['updated'].' '.($summary['updated'] === 1 ? 'student/pupil' : 'students/pupils').' updated ('.($summary['fields'] ?? 0).' '.(($summary['fields'] ?? 0) === 1 ? 'change' : 'changes').')';
        }

        if (($summary['added'] ?? 0) > 0) {
            $parts[] = $summary['added'].' added';
        }

        if (($summary['removed'] ?? 0) > 0) {
            $parts[] = $summary['removed'].' removed from the class';
        }

        if (($summary['columns'] ?? 0) > 0) {
            $parts[] = $summary['columns'].' '.($summary['columns'] === 1 ? 'column' : 'columns').' added';
        }

        return $parts === [] ? 'No changes' : implode(', ', $parts);
    }
}
