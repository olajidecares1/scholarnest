<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $schoolId = $request->user()->school_id;

        if (mb_strlen($query) < 2) {
            return response()->json(['students' => [], 'staff' => [], 'classes' => []]);
        }

        $students = Student::where('school_id', $schoolId)
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orWhere('admission_number', 'like', "%{$query}%"))
            ->limit(5)
            ->get()
            ->map(fn (Student $student) => [
                'title' => $student->fullName(),
                'subtitle' => $student->admission_number,
                'url' => route('students.show', $student),
            ]);

        $staff = Staff::where('school_id', $schoolId)
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orWhere('staff_number', 'like', "%{$query}%"))
            ->limit(5)
            ->get()
            ->map(fn (Staff $member) => [
                'title' => $member->fullName(),
                'subtitle' => $member->staff_number,
                'url' => route('staff.show', $member),
            ]);

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn (SchoolClass $class) => [
                'title' => $class->name,
                'subtitle' => 'Class',
                'url' => route('students.index', ['class' => $class->name]),
            ]);

        return response()->json([
            'students' => $students,
            'staff' => $staff,
            'classes' => $classes,
        ]);
    }
}
