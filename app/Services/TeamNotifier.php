<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Telling the EduNest Team something, exactly once.
 *
 * Every notification to the team goes through here, and every one of them
 * names the EVENT it is about - "school.registered:<uuid>", not "a
 * notification". That is what makes "one registration, one notification" a
 * property of the system rather than a promise made by whichever controller
 * happens to be sending.
 *
 * The claim comes first and the sending second. A row in notification_events
 * is inserted against a unique index before anyone is notified, so a second
 * attempt for the same event loses the insert and stops there. The obvious
 * alternative - ask whether it has been sent, then send - leaves a gap between
 * the question and the answer, and two requests arriving together both pass
 * through it. This has no gap.
 *
 * That covers every cause worth worrying about: a double-clicked form, a
 * refreshed POST, a retried request, a job delivered twice, and a listener
 * accidentally registered twice.
 */
class TeamNotifier
{
    /**
     * Notify the team about an event, unless it has been announced already.
     *
     * @param  string  $eventKey  Stable and unique for the event - not for the
     *                            attempt. "school.registered:<school uuid>".
     * @return bool Whether this call was the one that sent it.
     */
    public function once(string $eventKey, Notification $notification): bool
    {
        if (! $this->claim($eventKey, $notification::class)) {
            return false;
        }

        $recipients = $this->team();

        // Worth a log line rather than silence. An event announced to nobody
        // is a platform with no Super Admin account, which looks exactly like
        // a broken notification until somebody checks.
        if ($recipients->isEmpty()) {
            Log::warning('No EduNest Team account to notify.', ['event' => $eventKey]);

            return true;
        }

        $recipients->each(fn (User $member) => $member->notify($notification));

        DB::table('notification_events')
            ->where('event_key', $eventKey)
            ->update(['recipients' => $recipients->count()]);

        return true;
    }

    /**
     * Has the team already been told about this?
     *
     * For tests and for anybody investigating; the sending path does not ask,
     * because asking is what the claim replaces.
     */
    public function alreadySent(string $eventKey): bool
    {
        return DB::table('notification_events')->where('event_key', $eventKey)->exists();
    }

    /**
     * Take the event, or discover somebody else already has.
     */
    private function claim(string $eventKey, string $notification): bool
    {
        try {
            DB::table('notification_events')->insert([
                'event_key' => $eventKey,
                'notification' => $notification,
                'created_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            // The unique index did its job. Any other database fault is a real
            // problem and must not be swallowed as "already sent".
            if ($this->isDuplicateKey($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function team(): Collection
    {
        return User::where('role', UserRole::SuperAdmin)->get();
    }

    /**
     * Integrity violation, across the drivers this application runs on.
     *
     * MySQL says 23000/1062 and SQLite says 23000/19; both set the SQLSTATE,
     * so that is what is checked rather than the driver's own number.
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique constraint')
            || str_contains(strtolower($e->getMessage()), 'duplicate entry');
    }
}
