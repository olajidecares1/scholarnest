<?php

namespace App\Console\Commands;

use App\Services\DefaultSubjects;
use Illuminate\Console\Command;

/**
 * Gives every school's Reception/KG, Nursery, Primary, JSS and SS classes the default subjects
 * listed in config/subjects.php. Safe to run as often as you like: a subject
 * a class already has, under any capitalisation, spacing or listed alias, is
 * never added a second time, and a subject taken off a list is removed from
 * a class only where no score has been recorded for it.
 *
 * The deploy migration runs the same work once; run this again after
 * editing config/subjects.php. Also answers to its first name,
 * subjects:backfill-jss.
 */
class BackfillJssSubjectsCommand extends Command
{
    protected $signature = 'subjects:backfill';

    /** @var list<string> */
    protected $aliases = ['subjects:backfill-jss'];

    protected $description = 'Give every school\'s KG, Nursery, Primary, JSS and SS classes the default subjects in config/subjects.php, without duplicates';

    public function handle(): int
    {
        $result = DefaultSubjects::backfillAll();

        $this->info("Checked {$result['schools']} school(s); added {$result['added']} subject(s).");

        return self::SUCCESS;
    }
}
