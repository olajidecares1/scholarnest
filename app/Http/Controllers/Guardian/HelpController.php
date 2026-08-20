<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(Request $request, School $school): View
    {
        return view('guardian.help.index', [
            'school' => $school,
            'guardian' => $request->user('guardian'),
            'website' => $school->website,
        ]);
    }
}
