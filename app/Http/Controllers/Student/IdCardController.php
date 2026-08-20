<?php

namespace App\Http\Controllers\Student;

use App\Enums\IdCardHolderType;
use App\Http\Controllers\Controller;
use App\Models\IssuedIdCard;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IdCardController extends Controller
{
    public function show(Request $request, School $school): View
    {
        return view('student.id-card.show', [
            'school' => $school,
            'student' => $request->user('student'),
        ]);
    }

    /**
     * Read-only: returns the student's own already-issued ID card, if the
     * School Admin has generated one. Deliberately has no print/pdf/regenerate
     * counterpart - those stay School-Admin-only capabilities, enforced by
     * their routes simply not existing under this guard.
     */
    public function preview(Request $request, School $school): JsonResponse
    {
        $student = $request->user('student');

        $card = IssuedIdCard::where('school_id', $school->id)
            ->where('holder_type', IdCardHolderType::Student)
            ->where('holder_uuid', $student->uuid)
            ->first();

        if (! $card || ! $card->template) {
            return response()->json([
                'has_template' => false,
                'card_number' => null,
                'front' => null,
                'back' => null,
            ]);
        }

        return response()->json([
            'has_template' => true,
            'card_number' => $card->card_number,
            'front' => view('school-admin.id-cards._card', ['card' => $card])->render(),
            'back' => view('school-admin.id-cards._card_back', ['card' => $card])->render(),
        ]);
    }
}
