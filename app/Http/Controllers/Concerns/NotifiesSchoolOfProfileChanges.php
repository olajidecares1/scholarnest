<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Notifications\PortalProfileUpdatedNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies a portal user's own edits, and tells the school about them.
 *
 * Staff and guardians keep their own contact details current because they are
 * the ones who know when those change. The records still belong to the school,
 * though, so the change is not allowed to happen quietly, a phone number that
 * moves without the office knowing is a call that bounces with no explanation.
 *
 * Shared by the staff and guardian portals so the two cannot end up notifying
 * differently, or one of them not at all.
 */
trait NotifiesSchoolOfProfileChanges
{
    /**
     * Save the changes and notify the school's administrators about the ones
     * that actually moved.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function applyProfileChanges(Model $person, array $changes, string $personName, string $personType): void
    {
        // Compared before saving, so the notification names what changed
        // rather than announcing an update every time somebody opens the form
        // and presses Update without editing anything.
        $changed = collect($changes)
            ->filter(fn ($value, $field) => (string) $person->getOriginal($field) !== (string) $value)
            ->keys()
            ->all();

        $person->update($changes);

        if ($changed === []) {
            return;
        }

        AuditLog::record(
            'portal.profile.updated',
            "{$personName} ({$personType}) updated their own ".implode(', ', $changed).'.',
            $person,
            actorName: $personName,
        );

        $school = $person->school instanceof School ? $person->school : null;

        if (! $school) {
            return;
        }

        User::where('school_id', $school->id)
            ->where('role', UserRole::SchoolAdmin)
            ->get()
            ->each(fn (User $admin) => $admin->notify(
                new PortalProfileUpdatedNotification($person, $personName, $personType, $changed),
            ));
    }
}
