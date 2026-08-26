<?php

namespace App\Notifications;

use App\Models\TeacherDiaryEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the teacher their entry has been read.
 *
 * The other half of the loop. A teacher who submits into silence has no way to
 * tell a diary that is being read from one that is not, and stops writing it
 * carefully.
 */
class DiaryEntrySeen extends Notification
{
    use Queueable;

    public function __construct(public TeacherDiaryEntry $entry) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your diary entry has been reviewed',
            'body' => sprintf(
                '%s · %s · Week %d was marked seen by %s.',
                $this->entry->subject,
                $this->entry->class_name,
                $this->entry->week_number,
                $this->entry->seenBy?->name ?? 'your school',
            ),
            'diary_entry_uuid' => $this->entry->uuid,
        ];
    }
}
