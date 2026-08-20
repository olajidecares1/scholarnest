<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request, School $school): View
    {
        return view('student.profile', [
            'school' => $school,
            'student' => $request->user('student'),
        ]);
    }
}
