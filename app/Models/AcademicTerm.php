<?php

namespace App\Models;

use App\Enums\ExamTerm;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicTerm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'session',
        'term',
        'starts_on',
        'ends_on',
        'school_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => ExamTerm::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'school_days' => 'integer',
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
     * The configured date range for a given school/session/term, or null if
     * the school admin hasn't set one yet, callers must handle that rather
     * than guessing a range.
     */
    public static function rangeFor(School $school, string $session, ExamTerm $term): ?self
    {
        return self::where('school_id', $school->id)
            ->where('session', $session)
            ->where('term', $term)
            ->first();
    }

    /**
     * The term the school is in today, if its calendar says so.
     *
     * Read from the dates the school entered rather than guessed from the
     * month, because a school that runs to its own calendar is exactly the
     * school a guess gets wrong. Null when today falls outside every recorded
     * term, the holidays, or a school that has not filled its calendar in,
     * and callers treat that as "no term is current" rather than inventing one.
     */
    public static function currentFor(School $school): ?self
    {
        return self::where('school_id', $school->id)
            ->where('session', $school->currentSession())
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->first();
    }

    /**
     * How many days the school opened this term.
     *
     * The number the School Admin entered when there is one. Otherwise the
     * weekdays (Monday to Friday) from the first day of term to the last,
     * which is right for a term with no holidays in it and an over-count by
     * one for every public holiday, hence the field.
     */
    public function schoolDays(): int
    {
        if ($this->school_days) {
            return (int) $this->school_days;
        }

        return self::weekdaysBetween($this->starts_on->copy(), $this->ends_on->copy());
    }

    /**
     * Did the School Admin type the number in, or is it worked out?
     */
    public function hasConfiguredSchoolDays(): bool
    {
        return (bool) $this->school_days;
    }

    public static function weekdaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        if ($to->lessThan($from)) {
            return 0;
        }

        $days = 0;

        for ($day = $from->copy()->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if (! $day->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }
}
