<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\View\View;

class PortalLockedController extends Controller
{
    public function show(School $school): View
    {
        return view('staff.locked', ['school' => $school]);
    }
}
