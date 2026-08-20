<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\View\View;

class PortalLockedController extends Controller
{
    public function show(School $school): View
    {
        return view('student.locked', ['school' => $school]);
    }
}
