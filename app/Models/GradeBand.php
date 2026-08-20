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
     * grade bands, or the app-wide default scale if it hasn't configured any
     * - so every school's grading behaves consistently until they set up
     * their own bands.
     */
    public static function resolve(School $school, float $percentage): string
    {
        return self::match($school, $percentage)['letter'];
    }

    /**
     * The remark/description for a percentage - the same band lookup as
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

        $default = collect(self::defaultBands())->first(fn (array $band) => $percentage >= $band['min_percent'] && $percentage <= $band['max_percent'])
            ?? collect(self::defaultBands())->last();

        return ['letter' => $default['letter'], 'description' => $default['description']];
    }
}
