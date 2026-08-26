<?php

namespace App\Services;

use App\Enums\PlanKey;
use App\Models\School;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns whatever someone types into the Basic portal's "Enter your school
 * name" box into the school they meant.
 *
 * Only Basic-plan schools are ever returned. A Standard or Exclusive school is
 * invisible here even if its name is typed exactly, because those plans have
 * their own way in - a subdomain or their own domain - and the whole point of
 * this portal is that Basic schools have neither.
 */
class BasicPortalSchoolFinder
{
    /**
     * Never return more than this many candidates. Someone typing a single
     * common word ("college") should get a short list to choose from, not a
     * directory of every Basic school on the platform.
     */
    private const MAX_MATCHES = 8;

    /**
     * The character that escapes a wildcard inside the LIKE patterns below.
     *
     * Deliberately '!' rather than the more usual backslash. MySQL and SQLite
     * disagree about backslashes inside string literals - MySQL unescapes
     * them, SQLite takes them literally - so `ESCAPE '\\'` means one character
     * to one engine and two to the other, and the second rejects it outright.
     * '!' is special to neither, so the same SQL behaves identically in
     * production (MySQL) and in the test suite (SQLite).
     */
    private const LIKE_ESCAPE = '!';

    /**
     * The LIKE comparison used by the two fuzzy tiers.
     *
     * The explicit ESCAPE clause is what makes escapeLike() below actually do
     * anything: without it, SQLite ignores the escaping entirely and a typed
     * '%' would still behave as a wildcard.
     */
    private const LIKE_NAME = "LOWER(name) LIKE ? ESCAPE '".self::LIKE_ESCAPE."'";

    /**
     * Find the Basic-plan schools matching a typed name.
     *
     * Matching runs from most precise to least, and stops at the first tier
     * that produces anything:
     *
     *   1. the school's code, slug, or exact name  - an unambiguous answer
     *   2. names starting with what was typed      - "greenfield" finds
     *                                                "Greenfield College"
     *   3. names containing what was typed         - the last resort
     *
     * Tiering matters: without it, a school named exactly "Kings College"
     * would be buried among every other school with "college" in its name.
     *
     * @return Collection<int, School>
     */
    public function search(string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        foreach ([
            fn (): Collection => $this->exactMatches($term),
            fn (): Collection => $this->prefixMatches($term),
            fn (): Collection => $this->containsMatches($term),
        ] as $tier) {
            $matches = $tier();

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        return collect();
    }

    /**
     * @return Collection<int, School>
     */
    private function exactMatches(string $term): Collection
    {
        return $this->basicSchools(function ($query) use ($term): void {
            $query->where('school_code', Str::upper($term))
                ->orWhere('slug', Str::slug($term))
                ->orWhereRaw('LOWER(name) = ?', [Str::lower($term)]);
        });
    }

    /**
     * @return Collection<int, School>
     */
    private function prefixMatches(string $term): Collection
    {
        return $this->basicSchools(function ($query) use ($term): void {
            $query->whereRaw(self::LIKE_NAME, [$this->escapeLike(Str::lower($term)).'%']);
        });
    }

    /**
     * @return Collection<int, School>
     */
    private function containsMatches(string $term): Collection
    {
        return $this->basicSchools(function ($query) use ($term): void {
            $query->whereRaw(self::LIKE_NAME, ['%'.$this->escapeLike(Str::lower($term)).'%']);
        });
    }

    /**
     * Run one matching tier and keep only the schools actually entitled to use
     * this portal.
     *
     * The plan check happens in PHP rather than SQL on purpose: hasPlanAccess()
     * is the single shared gate used by every plan-gated feature in the app,
     * and duplicating its rules as a join here would mean two definitions of
     * "is on the Basic plan" that could drift apart.
     *
     * @param  \Closure(Builder): void  $constrain
     * @return Collection<int, School>
     */
    private function basicSchools(\Closure $constrain): Collection
    {
        return School::query()
            ->with('activeSubscription.plan')
            ->where('is_active', true)
            ->where($constrain)
            ->orderBy('name')
            // Fetch a little more than we need, because the plan filter below
            // may discard some of them.
            ->limit(self::MAX_MATCHES * 4)
            ->get()
            ->filter(fn (School $school): bool => $school->hasPlanAccess(PlanKey::Basic))
            ->take(self::MAX_MATCHES)
            ->values();
    }

    /**
     * Neutralise the wildcards inside a LIKE pattern.
     *
     * Without this, typing "%" would match every school on the platform, and
     * "_" would match any single character - turning the finder into a way to
     * enumerate EduNest's customers.
     */
    private function escapeLike(string $value): string
    {
        $e = self::LIKE_ESCAPE;

        // The escape character itself must be escaped first, otherwise the
        // escapes added for % and _ would themselves be re-escaped.
        return str_replace(
            [$e, '%', '_'],
            [$e.$e, $e.'%', $e.'_'],
            $value,
        );
    }
}
