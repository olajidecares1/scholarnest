<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $guardian = $request->user('guardian');

        $notifications = $guardian->notifications()->paginate(15);

        $guardian->unreadNotifications->markAsRead();

        return view('guardian.notifications.index', [
            'school' => $school,
            'notifications' => $notifications,
        ]);
    }
}
