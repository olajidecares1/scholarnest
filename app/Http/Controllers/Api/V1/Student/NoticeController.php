<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Enums\MemorandumAudience;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SchoolNoticeResource;
use App\Models\SchoolNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NoticeController extends Controller
{
    /**
     * Memoranda addressed to this student.
     *
     * Two filters, and both are the rule rather than a convenience: the
     * audience, so a note to the staff room is not read by a pupil, and the
     * class, so a note to one class is not read by another.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user();

        $notices = SchoolNotice::query()
            ->where('school_id', $student->school_id)
            ->forAudience(MemorandumAudience::Students)
            ->where(fn ($query) => $query->whereNull('class_name')->orWhere('class_name', $student->class_name))
            ->latest()
            ->paginate($request->integer('per_page') ?: 25);

        return SchoolNoticeResource::collection($notices);
    }
}
