<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(): View
    {
        return view('school-admin.communications.index', [
            'announcements' => Announcement::with('sentBy')->latest()->paginate(10),
        ]);
    }
}
