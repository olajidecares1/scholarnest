<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Is anything actually running the queue?
 *
 * CBT extraction is queued, which is right - parsing a 20MB PDF inside a web
 * request would block it for a minute or more. But queued work only happens if
 * a worker is running, and `php artisan serve` on its own does not start one
 * (`composer run dev` does). When none is running the upload succeeds, the job
 * is written, and the page waits on work that will never begin.
 *
 * This used to be inferred: a job that had sat unclaimed for two minutes was
 * taken as evidence nothing was collecting. That inference is sound but slow,
 * and slow is the whole complaint - a freshly queued job is not yet old, so
 * someone who uploaded a document with no worker running watched "Waiting to
 * start" for over two minutes before being told anything was wrong.
 *
 * So a worker now says so itself. Every pass of its loop stamps a heartbeat,
 * which makes "is a worker running" a question with an answer rather than a
 * guess that needs two minutes of silence to make.
 */
class QueueWorkerHealth
{
    /**
     * How recently a worker must have stamped its heartbeat to count as alive.
     *
     * The stamp is written on every loop, which for an idle worker is roughly
     * once a second, so a minute is many missed beats rather than a near miss.
     */
    private const HEARTBEAT_TTL_SECONDS = 60;

    /**
     * Grace for a worker that is starting up and has not stamped yet.
     *
     * A running worker claims a waiting job in well under a second. Twenty
     * seconds is generous to a cold start and still tells the truth six times
     * faster than the two minutes this replaced.
     */
    private const STALE_AFTER_SECONDS = 20;

    private const HEARTBEAT_KEY = 'queue-worker-heartbeat';

    private const CACHE_KEY = 'queue-worker-health';

    /**
     * Called by the worker itself, once per loop.
     */
    public static function heartbeat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now()->timestamp, now()->addSeconds(self::HEARTBEAT_TTL_SECONDS * 3));
    }

    /**
     * Whether the queue appears to be running.
     *
     * Cached briefly: this is asked on page loads and on every poll, and the
     * answer cannot meaningfully change several times a second. Five seconds
     * rather than fifteen, because this number is added to how long someone
     * stares at a progress bar before being told the truth.
     */
    public function isRunning(): bool
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(5), function (): bool {
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

            // Nothing waiting, nothing to be wrong about.
            if ($oldestUnclaimed === null) {
                return true;
            }

            if ($this->heartbeatIsRecent()) {
                return true;
            }

            // No heartbeat. Either no worker, or one that has only just been
            // started - so a short grace before calling it stalled.
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
     * What to tell someone whose document is waiting on a queue that is not
     * running.
     *
     * Two audiences, because the useful sentence differs. The AkademicNest Team can
     * start a worker, so they are told which command does it. A teacher cannot
     * and should not be shown a shell command - they are told plainly that the
     * document is safe and who can fix it, which is the difference between an
     * outage they can act on and one they can only stare at.
     */
    public function stalledMessage(bool $canOperateTheServer): string
    {
        if ($canOperateTheServer) {
            return 'Extraction has not started: nothing is processing queued jobs. '
                ."Start a worker with \"php artisan queue:work\" (or run \"composer run dev\", which starts one alongside the server). {$this->pendingJobs()} job(s) are waiting.";
        }

        return 'Extraction has not started yet because the extraction service is not running. '
            .'Your document has been saved - it will be processed once the service is back, '
            .'and you do not need to upload it again. Please tell the AkademicNest Team if it stays this way.';
    }

    /**
     * Forget the cached answer - used the moment a worker's absence would
     * change what a page says.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function heartbeatIsRecent(): bool
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        if ($beat === null) {
            return false;
        }

        return (now()->timestamp - (int) $beat) <= self::HEARTBEAT_TTL_SECONDS;
    }
}
