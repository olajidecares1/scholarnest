<?php

namespace App\Console\Commands;

use App\Services\DefaultClassSubjects;
use Illuminate\Console\Command;

/**
 * Gives every school's classes, Reception to SS 3, the default subjects
 * listed in config/subjects.php, and brings this session's examinations in
 * line so the subjects reach the teachers' score sheets. Safe to run as
 * often as you like: a subject a class already has, under any
 * capitalisation, spacing or listed alias, is never added a second time.
 *
 * The deploy migration runs the same work once; run this again after
 * editing the lists in config/subjects.php.
 */
class BackfillDefaultSubjectsCommand extends Command
{
    protected $signature = 'subjects:backfill-defaults';

    /**
     * The old name, from when only JSS classes had defaults.
     *
     * @var list<string>
     */
    protected $aliases = ['subjects:backfill-jss'];

    protected $description = 'Add the default subjects (config/subjects.php) to every school\'s classes, without duplicates';

    public function handle(): int
    {
        $result = DefaultClassSubjects::backfillAll();

        $this->info("Checked {$result['schools']} school(s); added {$result['added']} default subject(s) to their classes.");

        return self::SUCCESS;
    }
}
