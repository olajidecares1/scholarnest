<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ResultDeliveryChannel;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Notifications\ResultDetailsNotification;
use App\Services\ExaminationResultCalculator;
use App\Services\ResultDetails\GuardianContacts;
use App\Services\ResultDetails\ResultDetailsSender;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WhatsApp broadcast of result details: the whole class, or the pupils
 * ticked on Results, each parent sent THEIR OWN child's details on WhatsApp
 * (name, admission number, token, and a link that opens the result
 * directly). No portal.
 *
 * This page is the summary before the one action. The send itself is
 * ResultDetailsController::send (channel WhatsApp), which takes each
 * parent's number from the pupil's own record, and
 * sends every message on its own: automatically when WhatsApp sending is
 * switched on, otherwise one tap per parent from the school's phone.
 *
 * Tokens are the School Admin's to generate, under Exam Tokens: a pupil
 * without one they can use is listed here and left out, never given one.
 */
class ResultBroadcastController extends Controller
{
    public function __construct(
        private readonly GuardianContacts $contacts,
        private readonly WhatsAppGateway $whatsApp,
        private readonly ResultDetailsSender $sender,
    ) {}

    public function show(Request $request, string $examination): View
    {
        $validated = $request->validate([
            'students' => ['nullable', 'array', 'max:500'],
            'students.*' => ['uuid'],
        ]);

        $school = $request->user()->school;
        $exam = Examination::query()->where('school_id', $school->id)->where('uuid', $examination)->firstOrFail();

        $chosen = $validated['students'] ?? [];
        $students = $chosen === []
            ? ExaminationResultCalculator::rosterFor($exam)
            : $school->students()->whereIn('uuid', $chosen)->orderBy('first_name')->get();

        // Only tokens the School Admin generated: nothing is made here.
        $tokens = $this->sender->tokensForResult($school, $exam, $students);
        $withToken = $tokens['ready']->pluck('bound_student_id')->all();

        $numbers = $students->mapWithKeys(fn (Student $student) => [
            $student->id => collect($this->contacts->forChannel($student, ResultDeliveryChannel::WhatsApp)),
        ]);

        $reachable = $students->filter(fn (Student $s) => $numbers[$s->id]->isNotEmpty() && in_array($s->id, $withToken, true));

        return view('school-admin.result-details.broadcast', [
            'school' => $school,
            'examination' => $exam,
            'students' => $students,
            'chosen' => $chosen,
            'reachable' => $reachable->count(),
            'messages' => $reachable->sum(fn (Student $s) => $numbers[$s->id]->count()),
            'missing' => $students->filter(fn (Student $s) => $numbers[$s->id]->isEmpty())->values(),
            'noToken' => $tokens['unusable'],
            'automatic' => $this->whatsApp->forSchool($school)->isConfigured(),
            'resultLinkLive' => $school->resultLinkIsLive(),
            'sample' => $this->sample($school, $exam, $reachable->first() ?? $students->first()),
        ]);
    }

    /** What one parent receives, with the token hidden. */
    private function sample(School $school, Examination $exam, ?Student $student): ?string
    {
        if ($student === null) {
            return null;
        }

        $contact = $this->contacts->forChannel($student, ResultDeliveryChannel::WhatsApp)[0] ?? null;

        return (new ResultDetailsNotification(
            $student,
            $exam,
            '••••••••••••',
            $school->resultLinkUrl().'#…',
            (string) ($contact['name'] ?? 'Parent/Guardian'),
            $school->verifiedWhatsApp(),
        ))->forWhatsApp()['text'];
    }
}
