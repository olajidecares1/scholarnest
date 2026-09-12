<?php

namespace App\Support;

use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Models\Plan;
use App\Models\Subject;
use Database\Seeders\CbtSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\SubjectSeeder;

/**
 * The catalogues the application cannot work without, put in place on a
 * database that has none.
 *
 * They were only ever created by seeders, and a deploy runs migrations, not
 * seeders. So production started with an empty plans table, and a school that
 * registered reached "Choose Your Plan" to find no plans at all and a Continue
 * button that could never be pressed. `db:seed` is no answer there:
 * DatabaseSeeder also creates a test@example.com user.
 *
 * EACH CATALOGUE ONLY WHEN IT IS EMPTY. The seeders use updateOrCreate, so
 * running them over existing rows would overwrite what the AkademicNest Team
 * has since changed - a plan price set in Super Admin, above all. An empty
 * table has nothing to overwrite; a table with anything in it is left exactly
 * as it is.
 */
final class ReferenceData
{
    /**
     * @return list<string> the catalogues that were empty and have been filled
     */
    public static function seedMissing(): array
    {
        $seeded = [];

        if (! Plan::query()->exists()) {
            (new PlanSeeder)->run();
            $seeded[] = 'plans';
        }

        if (! Subject::query()->exists()) {
            (new SubjectSeeder)->run();
            $seeded[] = 'subjects';
        }

        // Both halves together: the seeder links exam bodies to subjects, so
        // filling one against a hand-built other could attach the wrong rows.
        if (! CbtExamBody::query()->exists() && ! CbtSubject::query()->exists()) {
            (new CbtSeeder)->run();
            $seeded[] = 'cbt';
        }

        return $seeded;
    }
}
