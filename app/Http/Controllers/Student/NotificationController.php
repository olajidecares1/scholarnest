<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $notifications = $student->notifications()->paginate(15);

        $student->unreadNotifications->markAsRead();

        return view('student.notifications.index', [
            'school' => $school,
            'notifications' => $notifications,
        ]);
    }
}
