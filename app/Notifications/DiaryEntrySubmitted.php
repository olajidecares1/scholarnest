<?php

namespace App\Notifications;

use App\Models\TeacherDiaryEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the school that a teacher has logged a week's topic.
 *
 * Sent to the School Admin rather than waiting to be found: a diary nobody is
 * told about is a diary nobody reads, and being read is the whole point of
 * submitting one.
 */
class DiaryEntrySubmitted extends Notification
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
            'title' => "{$this->entry->teacher->fullName()} submitted a diary entry",
            'body' => sprintf(
                '%s · %s · Week %d, %s %s',
                $this->entry->subject,
                $this->entry->class_name,
                $this->entry->week_number,
                $this->entry->term->label(),
                $this->entry->session,
            ),
            'diary_entry_uuid' => $this->entry->uuid,
        ];
    }
}
