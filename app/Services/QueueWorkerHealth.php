<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Is anything actually running the queue?
 *
 * CBT extraction is queued, which is right - parsing a 20MB PDF inside a web
 * request would block it for a minute or more. But queued work only happens if
 * a worker is running, and when none is the application has no way of knowing:
 * the upload succeeds, the job is written, and the page sits at "Pending"
 * forever. To the person who uploaded it that is indistinguishable from a slow
 * or broken upload, and it is the reason this class exists.
 *
 * Rather than assert a worker is there, we look: a job that has sat in the
 * table well past the point one would have collected it is evidence that
 * nothing is collecting.
 */
class QueueWorkerHealth
{
    /**
     * How long a job may sit unclaimed before we stop assuming a worker will
     * take it. Generous - a busy worker can be a minute behind - but far short
     * of the hours a genuinely stalled queue accumulates.
     */
    private const STALE_AFTER_SECONDS = 120;

    private const CACHE_KEY = 'queue-worker-health';

    /**
     * Whether the queue appears to be running.
     *
     * Cached briefly: this is asked on page loads and during polling, and the
     * answer cannot meaningfully change several times a second.
     */
    public function isRunning(): bool
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(15), function (): bool {
            // Only the database driver can be inspected this way. On sync
            // there is no queue to stall, and on a remote broker the job table
            // is not ours to read - in both cases, assume it is fine rather
            // than warn about something we cannot see.
            if (config('queue.default') !== 'database') {
                return true;
            }

            $oldestUnclaimed = DB::table('jobs')
                ->whereNull('reserved_at')
                ->min('available_at');

            if ($oldestUnclaimed === null) {
                return true;
            }

            return (now()->timestamp - (int) $oldestUnclaimed) < self::STALE_AFTER_SECONDS;
        });
    }

    /**
     * How many jobs are waiting, for a message that says something concrete
     * rather than "there may be a problem".
     */
    public function pendingJobs(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        return DB::table('jobs')->count();
    }

    /**
     * Forget the cached answer - used the moment a worker's absence would
     * change what a page says.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
