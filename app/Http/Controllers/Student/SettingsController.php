<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        return view('student.settings.index', [
            'school' => $school,
            'student' => $student,
            'protectedFields' => ProfileChangeRequestController::protectedFields(),
            'changeRequests' => ProfileChangeRequest::where('school_id', $school->id)
                ->where('requester_type', 'student')
                ->where('requester_uuid', $student->uuid)
                ->latest()
                ->get(),
        ]);
    }
}
