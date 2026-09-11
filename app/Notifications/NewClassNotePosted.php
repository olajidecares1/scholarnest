<?php

namespace App\Notifications;

use App\Models\ClassNote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "Your teacher has sent a new class note."
 *
 * Database only, like every other portal notification here - it appears in the
 * pupil's own notifications list rather than in their inbox at home.
 *
 * The URL is what makes it useful: tapping the notification opens the note
 * itself. It is built from the note's own school, so a notification can only
 * ever point at the note it was raised for.
 */
class NewClassNotePosted extends Notification
{
    use Queueable;

    public function __construct(
        public ClassNote $note,
        public string $className,
    ) {}

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
        $subject = $this->note->subject ? $this->note->subject.' — ' : '';

        return [
            'title' => 'New Class Note: '.$this->note->title,
            'body' => $subject."Sent to {$this->className}. Open it to read or download the document.",

            // Stored rather than built when the list is rendered: the pupil
            // reading this next term should land on the note even if the
            // route has moved on.
            'url' => route('student.class-notes.show', [$this->note->school, $this->note]),
        ];
    }
}
