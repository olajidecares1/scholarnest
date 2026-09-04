<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\School;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An enquiry from a school's public website.
 *
 * Open, like the conduct report beside it, and scoped the same way: the school
 * is the one in the URL, never a field in the form.
 */
class PublicContactMessageController extends Controller
{
    public function store(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'name.required' => 'Please tell us your name.',
            'message.required' => 'Please write your message.',
        ]);

        $message = ContactMessage::create([
            'school_id' => $school->id,
            ...$validated,
        ]);

        User::query()
            ->where('school_id', $school->id)
            ->where('role', UserRole::SchoolAdmin)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new ContactMessageReceivedNotification($message)));

        return back()
            ->with('contact_status', 'Thank you. Your message has been sent to the school.')
            ->withFragment('contact');
    }
}
