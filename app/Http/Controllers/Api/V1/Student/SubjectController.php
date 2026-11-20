<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * The subjects this student's class offers, in result-sheet order. A KG,
     * JSS or SS class always lists its default subjects (is_default: true).
     *
     * Not paginated: a class offers a couple of dozen subjects at most, and
     * the mobile app caches the whole list for offline use.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        return response()->json([
            'data' => $student->school->classSubjectsFor((string) $student->class_name)->values(),
        ]);
    }
}
