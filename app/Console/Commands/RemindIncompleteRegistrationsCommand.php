<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\School;
use App\Notifications\CompleteYourRegistrationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Email schools that started registering and stopped.
 *
 * Runs on the schedule. Three things keep it from becoming a nuisance, and
 * all three matter:
 *
 *   ONCE PER SCHOOL. registration_reminder_sent_at is stamped whether or not
 *   the school ever replies, and the query excludes anything already stamped.
 *   A cron that fires hourly must not send an hourly email.
 *
 *   A FLOOR ON AGE. Registrations older than the configured window are left
 *   alone, so the first run after this ships does not mail every school that
 *   ever abandoned a signup, however long ago.
 *
 *   A CEILING PER RUN. A backlog goes out in batches rather than in one burst
 *   at the mail provider.
 */
class RemindIncompleteRegistrationsCommand extends Command
{
    protected $signature = 'registrations:remind-incomplete
                            {--dry-run : List who would be emailed without sending anything}';

    protected $description = 'Email schools that registered but never completed their subscription';

    public function handle(): int
    {
        $afterHours = (int) config('registration.reminder_after_hours', 24);
        $windowDays = (int) config('registration.reminder_window_days', 30);
        $batchSize = (int) config('registration.reminder_batch_size', 100);

        $schools = School::query()
            ->whereNull('registration_reminder_sent_at')

            // Old enough to have stopped, recent enough that the email is
            // still about something they remember doing.
            ->where('created_at', '<=', now()->subHours($afterHours))
            ->where('created_at', '>=', now()->subDays($windowDays))

            // The definition of "incomplete": no subscription of any kind.
            // See School::hasCompletedRegistration().
            ->whereDoesntHave('subscriptions')

            // Somebody has to receive it. A school with no administrator
            // account is a broken registration of a different sort, and
            // there is nowhere to send this.
            ->whereHas('users', fn ($query) => $query->where('role', UserRole::SchoolAdmin))

            ->with(['users' => fn ($query) => $query->where('role', UserRole::SchoolAdmin)])
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        if ($schools->isEmpty()) {
            $this->info('No incomplete registrations to remind.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($schools as $school) {
            $this->line(($dryRun ? '[dry run] ' : '').$school->name.', registered '.$school->created_at->diffForHumans());

            if ($dryRun) {
                continue;
            }

            // Stamped BEFORE sending, not after. If the mail driver throws
            // halfway through a batch, the run that follows must not start
            // again at the top and send this school a second copy. One
            // reminder that failed to send is a smaller problem than a school
            // receiving four.
            $school->forceFill(['registration_reminder_sent_at' => now()])->save();

            $school->users->each(
                fn ($user) => $user->notify(new CompleteYourRegistrationNotification($school))
            );

            Log::info('Incomplete registration reminder sent.', [
                'school_id' => $school->id,
                'registered_at' => $school->created_at->toIso8601String(),
            ]);
        }

        $this->info(($dryRun ? 'Would remind ' : 'Reminded ').$schools->count().' school(s).');

        return self::SUCCESS;
    }
}
