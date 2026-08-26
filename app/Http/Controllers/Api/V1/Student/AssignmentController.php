<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssignmentResource;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssignmentController extends Controller
{
    /**
     * Homework set for this student's class, soonest due first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user();

        $assignments = Assignment::query()
            ->where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->orderBy('due_date')
            ->paginate($request->integer('per_page') ?: 25);

        return AssignmentResource::collection($assignments);
    }
}
