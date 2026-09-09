<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns whatever someone types into the portal's "Enter your school name" box
 * into the school they meant.
 *
 * Any school on any plan, provided its subscription is active - see
 * findableSchools() for why this stopped being Basic-only. A Standard or
 * Exclusive school still has its own subdomain; this is simply the way in for
 * anybody who does not have that address to hand.
 */
class PortalSchoolFinder
{
    /**
     * Never return more than this many candidates. Someone typing a single
     * common word ("college") should get a short list to choose from, not a
     * directory of every school on the platform.
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
     * Find the schools matching a typed name.
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
        return $this->findableSchools(function ($query) use ($term): void {
            $query->where('school_code', Str::upper($term))
                ->orWhere('slug', Str::slug($term))
                ->orWhereRaw('LOWER(name) = ?', [Str::lower($term)])

                // The email the school registered with, matched EXACTLY and
                // only in this tier. A school's own people reach for their
                // email long before they remember a school code, and two
                // schools with similar names are told apart by it instantly.
                //
                // Exact only, deliberately: a partial match on email would
                // turn this box into a way to ask "who here uses gmail?" and
                // walk the customer list a domain at a time.
                ->orWhereRaw('LOWER(billing_email) = ?', [Str::lower($term)]);
        });
    }

    /**
     * @return Collection<int, School>
     */
    private function prefixMatches(string $term): Collection
    {
        return $this->findableSchools(function ($query) use ($term): void {
            $query->whereRaw(self::LIKE_NAME, [$this->escapeLike(Str::lower($term)).'%']);
        });
    }

    /**
     * @return Collection<int, School>
     */
    private function containsMatches(string $term): Collection
    {
        return $this->findableSchools(function ($query) use ($term): void {
            $query->whereRaw(self::LIKE_NAME, ['%'.$this->escapeLike(Str::lower($term)).'%']);
        });
    }

    /**
     * Run one matching tier and keep only the schools actually entitled to use
     * this portal.
     *
     * EVERY PLAN, not just Basic. This used to return Basic schools only, on
     * the reasoning that Standard and Exclusive schools are reached at their
     * own subdomain and never need to be looked up by name. True, and it left
     * every other school with no way in but a URL somebody had to have kept:
     * a Standard school that mislaid its subdomain had nowhere to type its own
     * name. Naming your school is now the way in on every plan, and WHICH
     * portals then appear is decided by the plan, on the page it lands on.
     *
     * An active subscription is still required. A school that has not been
     * approved yet has nothing behind any of those doors.
     *
     * The check happens in PHP rather than SQL on purpose: hasActiveSubscription()
     * is the single shared gate used by every plan-gated feature in the app,
     * and duplicating its rules as a join here would mean two definitions that
     * could drift apart.
     *
     * @param  \Closure(Builder): void  $constrain
     * @return Collection<int, School>
     */
    private function findableSchools(\Closure $constrain): Collection
    {
        return School::query()
            ->with('activeSubscription.plan')
            ->where('is_active', true)
            ->where($constrain)
            ->orderBy('name')
            // Fetch a little more than we need, because the filter below may
            // discard some of them.
            ->limit(self::MAX_MATCHES * 4)
            ->get()
            ->filter(fn (School $school): bool => $school->hasActiveSubscription())
            ->take(self::MAX_MATCHES)
            ->values();
    }

    /**
     * Neutralise the wildcards inside a LIKE pattern.
     *
     * Without this, typing "%" would match every school on the platform, and
     * "_" would match any single character - turning the finder into a way to
     * enumerate AkademicNest's customers.
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
