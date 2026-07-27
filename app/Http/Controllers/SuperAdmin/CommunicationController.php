<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(): View
    {
        return view('super-admin.communications.index', [
            'announcements' => Announcement::with('sentBy')->latest()->paginate(10),
            'schoolAdminCount' => User::where('role', UserRole::SchoolAdmin)->count(),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $schoolAdmins = User::where('role', UserRole::SchoolAdmin)->get();

        $announcement = Announcement::create([
            ...$request->validated(),
            'sent_by' => auth()->id(),
            'recipients_count' => $schoolAdmins->count(),
        ]);

        $schoolAdmins->each(fn (User $admin) => $admin->notify(new AnnouncementNotification($announcement)));

        AuditLog::record('announcement.sent', "Sent announcement \"{$announcement->title}\" to {$schoolAdmins->count()} school admin(s).", $announcement);

        return redirect()->route('super-admin.communications.index')
            ->with('status', "Announcement sent to {$schoolAdmins->count()} school admin(s).");
    }
}
