<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\UnlocksResultsWithToken;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use UnlocksResultsWithToken;

    public function show(Request $request, School $school): View
    {
        $student = $request->user('student');

        return view('student.profile', [
            'school' => $school,
            'student' => $student,

            // For the Check Result card: how many results are already open,
            // and how many are still waiting for their token.
            'resultLocks' => $this->resultLockSummary($student),
        ]);
    }
}
