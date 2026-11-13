<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Support\SchoolAddress;
use Illuminate\Console\Command;

/**
 * Schools that were registered at the same address before duplicates were
 * refused.
 *
 * Read only, deliberately. Two schools at one address may be one school that
 * registered twice, or two genuinely separate schools that share a compound
 * (a nursery and a secondary school, say). Only a person can tell, so this
 * lists them, oldest first, with what is needed to decide (which one is
 * subscribed, which one has students), and changes nothing. Merging or
 * deleting is a decision for the AkademicNest Team, made school by school.
 */
class ReportDuplicateSchoolAddresses extends Command
{
    protected $signature = 'schools:duplicate-addresses';

    protected $description = 'List schools that share a physical address (read only: nothing is changed)';

    public function handle(): int
    {
        $groups = [];

        School::query()
            ->whereNotNull('contact_address')
            ->withCount(['students', 'users'])
            ->with('activeSubscription')
            ->orderBy('id')
            ->chunkById(500, function ($schools) use (&$groups) {
                foreach ($schools as $school) {
                    if (($normal = SchoolAddress::normalise($school->contact_address)) !== null) {
                        $groups[$normal][] = $school;
                    }
                }
            });

        $duplicates = array_filter($groups, fn (array $schools) => count($schools) > 1);

        if ($duplicates === []) {
            $this->components->info('No two schools share an address.');

            return self::SUCCESS;
        }

        $this->components->warn(count($duplicates).' address(es) are shared by more than one school. Nothing has been changed.');

        foreach ($duplicates as $normal => $schools) {
            $this->newLine();
            $this->line("<options=bold>{$schools[0]->contact_address}</>");

            $this->table(
                ['ID', 'School', 'Registered', 'Active plan', 'Students', 'Users', 'Holds the address'],
                array_map(fn (School $school) => [
                    $school->id,
                    $school->name,
                    $school->created_at?->format('j M Y'),
                    $school->activeSubscription ? 'yes' : 'no',
                    $school->students_count,
                    $school->users_count,
                    $school->address_fingerprint !== null ? 'yes' : 'no',
                ], $schools),
            );
        }

        $this->line('The first school at each address holds it; the others keep working but cannot change to it. Review each pair before merging or removing anything.');

        return self::SUCCESS;
    }
}
