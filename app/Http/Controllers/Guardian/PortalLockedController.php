<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\View\View;

class PortalLockedController extends Controller
{
    public function show(School $school): View
    {
        return view('guardian.locked', ['school' => $school]);
    }
}
