<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(Request $request, School $school): View
    {
        return view('staff.help.index', [
            'school' => $school,
            'staff' => $request->user('staff'),
            'website' => $school->website,
        ]);
    }
}
