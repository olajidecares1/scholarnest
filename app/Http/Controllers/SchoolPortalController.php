<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\View\View;

class SchoolPortalController extends Controller
{
    /**
     * Show the school's Portal hub, a single entry point linking out to
     * each role's own school-scoped login, so a school's public website
     * never has to send anyone to the shared global AkademicNest login.
     */
    public function index(School $school): View
    {
        return view('school-portal.index', ['school' => $school]);
    }
}
