<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request, School $school): View
    {
        return view('staff.profile', [
            'school' => $school,
            'staff' => $request->user('staff'),
        ]);
    }
}
