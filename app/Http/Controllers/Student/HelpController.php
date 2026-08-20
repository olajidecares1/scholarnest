<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(Request $request, School $school): View
    {
        return view('student.help.index', [
            'school' => $school,
            'student' => $request->user('student'),
            'website' => $school->website,
        ]);
    }
}
