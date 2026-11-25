<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\PlanFeature;
use App\Enums\ResultDeliveryChannel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Notifications\ResultTokenIssuedNotification;
use App\Services\ExaminationResultCalculator;
use App\Services\ResultDetails\GuardianContacts;
use App\Services\ResultDetails\ResultDetailsSender;
use App\Support\WhatsAppNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A WhatsApp broadcast for one class's result: ONE message, the same for
 * every parent, sent once from the school's own phone to a WhatsApp
 * broadcast list. Free, and every parent gets it at the same moment.
 *
 * A broadcast cannot carry each child's token (every parent would see every
 * token), so the token goes where only that child's parents can see it:
 * their parent portal (postToPortals). The broadcast tells them the results
 * are out and where to look. The parents' numbers come as a contacts file,
 * so the school can save them all at once: WhatsApp only delivers a
 * broadcast to people who have saved the sender's number, and the sender
 * picks them from their own contacts.
 */
class ResultBroadcastController extends Controller
{
    public function __construct(
        private readonly GuardianContacts $contacts,
        private readonly ResultDetailsSender $sender,
    ) {}

    public function show(Request $request, string $examination): View
    {
        $school = $request->user()->school;
        $exam = $this->examination($school, $examination);
        $students = ExaminationResultCalculator::rosterFor($exam)->load('guardians');
        $portals = $this->hasPortals($school);

        $parents = $this->parentNumbers($students);
        $withAccount = $students->filter(fn (Student $s) => $s->guardians->isNotEmpty());

        return view('school-admin.result-details.broadcast', [
            'school' => $school,
            'examination' => $exam,
            'pupils' => $students->count(),
            'parentNumbers' => $parents->count(),
            'pupilsWithoutNumber' => $students->count() - $parents->flatMap(fn ($p) => $p['students'])->unique()->count(),
            'portals' => $portals,
            'withAccount' => $withAccount->count(),
            'withoutAccount' => $students->diff($withAccount)->map->fullName()->values(),
            'resultLinkLive' => $school->resultLinkIsLive(),
            'message' => $this->message($school, $exam, $portals),
        ]);
    }

    /**
     * Put each pupil's token in their own portal and their linked parents'.
     * A pupil with no token is issued one; a used-up one is replaced. Nobody
     * is sent the same token twice.
     */
    public function postToPortals(Request $request, string $examination): RedirectResponse
    {
        $school = $request->user()->school;
        $exam = $this->examination($school, $examination);
        abort_unless($this->hasPortals($school), 403);

        $students = ExaminationResultCalculator::rosterFor($exam);
        $selection = $this->sender->tokensForResult($school, $exam, $students, $request->user());

        $posted = 0;
        $parents = 0;

        foreach ($selection['ready'] as $pin) {
            $student = $pin->boundStudent;
            $plain = (string) $pin->plainToken();

            if ($student === null || $plain === '') {
                continue;
            }

            foreach ([$student, ...$student->guardians()->get()] as $recipient) {
                if ($recipient->notifications()->where('type', ResultTokenIssuedNotification::class)->where('data->token', $plain)->exists()) {
                    continue;
                }

                $recipient->notify(new ResultTokenIssuedNotification($exam, $student, $plain));
                $recipient instanceof Student ? null : $parents++;
            }

            $posted++;
        }

        AuditLog::record(
            'result-token.broadcast',
            "Posted {$exam->class_name} {$exam->term->label()} {$exam->session} result tokens to {$posted} pupil(s)' portals and their linked parents, for a WhatsApp broadcast.",
            $school,
        );

        $status = "Done: {$posted} ".Str::plural('pupil', $posted)."'s tokens are in their portals".($parents > 0 ? ", and in {$parents} parent ".Str::plural('portal', $parents).' for the first time' : '').'.';

        if ($selection['unusable']->isNotEmpty()) {
            $status .= ' Not posted: '.$selection['unusable']->map(fn ($u) => "{$u['name']} ({$u['reason']})")->join('; ').'.';
        }

        return redirect()->route('result-pins.details.broadcast', $exam->uuid)->with('status', $status);
    }

    /**
     * Every parent's WhatsApp number in the class, as one contacts file
     * (.vcf): opened on the school's phone, it saves them all at once.
     */
    public function contacts(Request $request, string $examination): Response
    {
        $school = $request->user()->school;
        $exam = $this->examination($school, $examination);
        $parents = $this->parentNumbers(ExaminationResultCalculator::rosterFor($exam));

        $cards = $parents->map(function (array $parent, string $digits) use ($school, $exam) {
            $name = $parent['name'].' ('.collect($parent['students'])->join(', ').')';

            return implode("\r\n", [
                'BEGIN:VCARD',
                'VERSION:3.0',
                'FN:'.$this->vcardText($name),
                'N:'.$this->vcardText($name).';;;;',
                'TEL;TYPE=CELL:'.'+'.$digits,
                'ORG:'.$this->vcardText($school->name),
                'NOTE:'.$this->vcardText("Parent/guardian, {$exam->class_name}"),
                'END:VCARD',
            ]);
        })->implode("\r\n");

        $file = Str::slug("{$school->name} {$exam->class_name} parents") ?: 'parents';

        return response($cards."\r\n", 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$file.'.vcf"',
        ]);
    }

    // -------------------------------------------------------------------------

    private function examination(School $school, string $uuid): Examination
    {
        return Examination::query()->where('school_id', $school->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function hasPortals(School $school): bool
    {
        return $school->hasPlanAccess(...PlanFeature::Guardians->requiredPlans());
    }

    /**
     * Each parent WhatsApp number once, with whose parent it is: a parent of
     * two pupils in the class is one contact.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<string, array{name: string, students: list<string>}>
     */
    private function parentNumbers(Collection $students): Collection
    {
        $numbers = [];

        foreach ($students as $student) {
            foreach ($this->contacts->forChannel($student, ResultDeliveryChannel::WhatsApp) as $contact) {
                $digits = (string) $contact['address'];
                $numbers[$digits] ??= ['name' => $contact['name'] ?: 'Parent/Guardian', 'students' => []];
                $numbers[$digits]['students'][] = $student->fullName();
            }
        }

        return collect($numbers);
    }

    /** The message, the same for every parent: no token in it. */
    private function message(School $school, Examination $exam, bool $portals): string
    {
        $intro = "Dear Parent/Guardian,\n\n{$school->name} has released the {$exam->term->label()} {$exam->session} results for {$exam->class_name}.\n\n";
        $resultLink = $school->resultLinkUrl();

        $body = $portals
            ? "To see your child's result:\n"
                .'1. Sign in to the parent portal: '.$this->guardianLogin($school)."\n"
                ."2. Open your notifications to find your child's result token.\n"
                ."3. Check the result with your child's admission number and the token: {$resultLink}\n\n"
                ."Can't sign in? Reply to this message."
            : "Your child's result token has been sent to you by email. Check the result with your child's admission number and the token: {$resultLink}\n\n"
                .'No token yet? Reply to this message.';

        $whatsApp = $school->verifiedWhatsApp();

        return $intro.$body.($whatsApp && ! str_contains($body, 'Reply') ? "\n\nWhatsApp: ".WhatsAppNumber::display($whatsApp) : '');
    }

    private function guardianLogin(School $school): string
    {
        return filled($school->portal_guardian_token)
            ? $school->portalLoginUrl('guardian')
            : route('portal.index', $school);
    }

    /** vCard text: commas, semicolons and backslashes escaped, one line. */
    private function vcardText(string $text): string
    {
        return str_replace(['\\', ',', ';', "\r", "\n"], ['\\\\', '\\,', '\\;', '', ' '], $text);
    }
}
