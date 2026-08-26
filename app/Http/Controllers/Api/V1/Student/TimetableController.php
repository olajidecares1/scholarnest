<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TimetableEntryResource;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimetableController extends Controller
{
    /**
     * The timetable for this student's class.
     *
     * Not paginated: a week's timetable is a fixed, small thing, and a client
     * that had to page through it could not draw a week.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user();

        $entries = TimetableEntry::query()
            ->where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->orderBy('day_of_week')
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get();

        return TimetableEntryResource::collection($entries);
    }
}
