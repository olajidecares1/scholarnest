<?php

namespace App\Console\Commands;

use App\Services\JuniorSecondarySubjects;
use Illuminate\Console\Command;

/**
 * Gives every school's JSS 1–3 classes the default subjects listed in
 * config/subjects.php. Safe to run as often as you like: a subject a class
 * already has, under any capitalisation, spacing or listed alias, is never
 * added a second time.
 *
 * The deploy migration runs the same work once; run this again after
 * editing the list in config/subjects.php.
 */
class BackfillJssSubjectsCommand extends Command
{
    protected $signature = 'subjects:backfill-jss';

    protected $description = 'Add the default JSS subjects (config/subjects.php) to every school\'s JSS 1–3 classes, without duplicates';

    public function handle(): int
    {
        $result = JuniorSecondarySubjects::backfillAll();

        $this->info("Checked {$result['schools']} school(s); added {$result['added']} subject(s) to JSS classes.");

        return self::SUCCESS;
    }
}
