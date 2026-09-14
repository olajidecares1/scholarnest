<?php

namespace App\Models;

use Database\Factories\GradeBandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeBand extends Model
{
    /** @use HasFactory<GradeBandFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'min_percent',
        'max_percent',
        'letter',
        'description',
        'position',
    ];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The letter grade for a percentage, using the given school's configured
     * grade bands, or the app-wide default scale if it hasn't configured any,
     * so every school's grading behaves consistently until they set up
     * their own bands.
     */
    public static function resolve(School $school, float $percentage): string
    {
        return self::match($school, $percentage)['letter'];
    }

    /**
     * The remark/description for a percentage, the same band lookup as
     * resolve(), just returning the description instead of the letter. Used
     * both for a subject's report-card remark and to render the Grade Key
     * box, so the key and the actual grading can never disagree.
     */
    public static function describe(School $school, float $percentage): string
    {
        return self::match($school, $percentage)['description'];
    }

    /**
     * The default grading scale used by every school that hasn't configured
     * its own grade bands.
     *
     * @return list<array{min_percent: int, max_percent: int, letter: string, description: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['min_percent' => 70, 'max_percent' => 100, 'letter' => 'A', 'description' => 'Excellent'],
            ['min_percent' => 60, 'max_percent' => 69, 'letter' => 'B', 'description' => 'Very Good'],
            ['min_percent' => 50, 'max_percent' => 59, 'letter' => 'C', 'description' => 'Good'],
            ['min_percent' => 40, 'max_percent' => 49, 'letter' => 'D', 'description' => 'Satisfactory'],
            ['min_percent' => 0, 'max_percent' => 39, 'letter' => 'E', 'description' => 'Needs Improvement'],
        ];
    }

    /**
     * @return array{letter: string, description: string}
     */
    private static function match(School $school, float $percentage): array
    {
        $band = $school->gradeBands
            ->sortBy('position')
            ->first(fn (self $band) => $percentage >= $band->min_percent && $percentage <= $band->max_percent);

        if ($band) {
            return ['letter' => $band->letter, 'description' => (string) $band->description];
        }

        // A school that has set up its own scale is graded on that scale and
        // nothing else. Falling back to the built-in one here would quietly mix
        // two grading systems on a single report card: a school whose bands
        // covered only 50-100 would have every failing mark graded on a scale
        // it never chose, and nothing on the page would say so. An uncovered
        // mark is a configuration gap, and it is shown as one.
        if ($school->gradeBands->isNotEmpty()) {
            return ['letter' => 'N/A', 'description' => 'Outside the school\'s grading scale'];
        }

        $default = collect(self::defaultBands())->first(fn (array $band) => $percentage >= $band['min_percent'] && $percentage <= $band['max_percent'])
            ?? collect(self::defaultBands())->last();

        return ['letter' => $default['letter'], 'description' => $default['description']];
    }

    /**
     * Percentage ranges this school's scale does not account for.
     *
     * Configuration is incremental, a school adding its bands one at a time
     * has gaps for as long as it takes, so this reports rather than forbids.
     * The grading page uses it to say what is still uncovered, which is the
     * difference between noticing on the settings screen and noticing on a
     * child's report card.
     *
     * @return list<array{from: int, to: int}>
     */
    public static function coverageGaps(School $school): array
    {
        // Queried, for the same reason as overlapsFor(): this is read straight
        // after a band is written, and a relation loaded before that write
        // would report gaps the school has just filled.
        $bands = $school->gradeBands()->get();

        if ($bands->isEmpty()) {
            return [];
        }

        $covered = [];

        foreach ($bands as $band) {
            for ($percent = (int) $band->min_percent; $percent <= (int) $band->max_percent; $percent++) {
                $covered[$percent] = true;
            }
        }

        $gaps = [];
        $start = null;

        // Walk 0-100 once, closing a gap whenever coverage resumes.
        for ($percent = 0; $percent <= 100; $percent++) {
            if (! isset($covered[$percent])) {
                $start ??= $percent;

                continue;
            }

            if ($start !== null) {
                $gaps[] = ['from' => $start, 'to' => $percent - 1];
                $start = null;
            }
        }

        if ($start !== null) {
            $gaps[] = ['from' => $start, 'to' => 100];
        }

        return $gaps;
    }

    /**
     * Bands whose ranges overlap each other.
     *
     * Two bands covering the same percentage make the grade depend on which
     * happens to sort first, which is not a decision anybody made.
     *
     * @param  int|null  $ignoreId  The band being edited, so it does not
     *                              collide with the row it is replacing.
     * @return list<string>
     */
    public static function overlapsFor(School $school, int $min, int $max, ?int $ignoreId = null): array
    {
        // Queried rather than read off the loaded relation. This decides
        // whether a write is allowed, and a relation cached earlier in the
        // request would answer from the state before that write.
        return $school->gradeBands()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('min_percent', '<=', $max)
            ->where('max_percent', '>=', $min)
            ->get()
            ->map(fn (self $band) => sprintf('%s (%d to %d%%)', $band->letter, $band->min_percent, $band->max_percent))
            ->values()
            ->all();
    }
}
