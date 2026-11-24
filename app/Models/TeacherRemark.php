<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One comment in a Class Teacher's list of comments they have written before.
 *
 * Nobody saves these by hand. Every comment a teacher settles on for a pupil
 * is remembered here; the half-typed drafts the comment box autosaves along
 * the way are forgotten again, see replace().
 */
class TeacherRemark extends Model
{
    use HasUuidRouteKey;

    protected $fillable = ['school_id', 'staff_id', 'body', 'body_hash', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public static function normalise(string $body): string
    {
        return trim(preg_replace('/\s+/u', ' ', $body));
    }

    public static function hashOf(string $body): string
    {
        return hash('sha256', mb_strtolower(self::normalise($body)));
    }

    /**
     * A pupil's comment changed from $old to $new: remember the new one and,
     * if the old one is now on nobody's report card, forget it.
     *
     * The comment box saves a second after the teacher stops typing, so
     * "Abibat is a" can be saved on the way to "Abibat is a careful worker."
     * The draft is written over on that same pupil's report, so no card
     * carries it any longer, and it leaves the list. A comment that is still
     * on some card, this term's or an earlier one's, stays.
     */
    public static function replace(Staff $staff, ?string $old, ?string $new): ?self
    {
        $old = self::normalise((string) $old);
        $new = self::normalise((string) $new);

        $kept = null;

        if ($new !== '') {
            $kept = static::query()->firstOrNew(['staff_id' => $staff->id, 'body_hash' => self::hashOf($new)]);
            $kept->fill(['school_id' => $staff->school_id, 'body' => $new, 'last_used_at' => now()])->save();
        }

        if ($old !== '' && self::hashOf($old) !== self::hashOf($new) && ! self::stillOnACard($staff, $old)) {
            static::query()->where('staff_id', $staff->id)->where('body_hash', self::hashOf($old))->delete();
        }

        return $kept;
    }

    /**
     * Comments already on report cards of this teacher's classes, from before
     * the list existed, so it does not start empty.
     *
     * @param  Collection<int, string>  $classNames
     */
    public static function learnFromClasses(Staff $staff, Collection $classNames): void
    {
        if ($classNames->isEmpty()) {
            return;
        }

        $known = static::query()->where('staff_id', $staff->id)->pluck('body_hash')->flip();

        ExaminationReport::query()
            ->where('examination_reports.school_id', $staff->school_id)
            ->whereNotNull('teacher_remark')
            ->whereIn('examination_id', Examination::query()
                ->where('school_id', $staff->school_id)
                ->whereIn('class_name', $classNames)
                ->select('id'))
            ->latest('updated_at')
            ->limit(500)
            ->get(['teacher_remark', 'updated_at'])
            ->each(function (ExaminationReport $report) use ($staff, $known) {
                $body = self::normalise((string) $report->teacher_remark);
                $hash = self::hashOf($body);

                if ($body === '' || $known->has($hash)) {
                    return;
                }

                $known->put($hash, true);
                static::query()->create([
                    'school_id' => $staff->school_id,
                    'staff_id' => $staff->id,
                    'body' => $body,
                    'body_hash' => $hash,
                    'last_used_at' => $report->updated_at,
                ]);
            });
    }

    /**
     * @return Collection<int, array{uuid: string, body: string}>
     */
    public static function listFor(Staff $staff): Collection
    {
        return static::query()
            ->where('staff_id', $staff->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['uuid', 'body'])
            ->map(fn (self $remark) => ['uuid' => $remark->uuid, 'body' => $remark->body])
            ->values();
    }

    private static function stillOnACard(Staff $staff, string $body): bool
    {
        // Compared in PHP, after a cheap prefix filter, so "Good work." and
        // "good  work. " count as the same comment, as they do in the list.
        return ExaminationReport::query()
            ->where('school_id', $staff->school_id)
            ->where('teacher_remark', 'like', mb_substr($body, 0, 20).'%')
            ->pluck('teacher_remark')
            ->contains(fn ($remark) => self::hashOf((string) $remark) === self::hashOf($body));
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
