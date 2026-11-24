<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Enums\IssuedIdCardStatus;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IdCardTemplate;
use App\Models\IssuedIdCard;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\IdentifierGenerator;
use App\Support\PreviousIdentifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class IdCardController extends Controller
{
    private const MM_TO_PT = 2.83464567;

    /**
     * ID Card Management.
     *
     * Students are found two ways: by class (every student in it, to
     * generate and print for all of them or for those ticked), or by
     * admission number (one student). Staff are listed by type, teaching
     * and non-teaching, and found by Staff ID. The admission number and the
     * Staff ID are the reference throughout: both are unique at the school
     * and never change (see Student and Staff).
     */
    public function index(Request $request): View
    {
        $school = $request->user()->school;
        $tab = in_array($request->query('tab'), ['student', 'teaching', 'non_teaching'], true) ? $request->query('tab') : 'student';

        $classes = $school->configuredClassNames();
        $selectedClass = in_array($request->query('class'), $classes, true) ? $request->query('class') : null;
        $classStudents = $selectedClass
            ? $school->students()->where('class_name', $selectedClass)->where('is_active', true)->alphabetical()->get()
            : collect();

        [$studentSearch, $studentMatches, $studentNotice] = $this->findStudents($school, trim((string) $request->query('admission')));

        $teachingStaff = $school->staff()->where('role', StaffRole::Teacher)->orderBy('first_name')->orderBy('last_name')->get();
        $nonTeachingStaff = $school->staff()->where('role', '!=', StaffRole::Teacher)->orderBy('first_name')->orderBy('last_name')->get();

        [$staffSearch, $staffMatch, $staffNotice] = $this->findStaff($school, trim((string) $request->query('staff_id')));

        if ($staffMatch) {
            $tab = $staffMatch->role === StaffRole::Teacher ? 'teaching' : 'non_teaching';
        }

        $holders = $classStudents->pluck('uuid')
            ->merge($studentMatches->pluck('uuid'))
            ->merge($teachingStaff->pluck('uuid'))
            ->merge($nonTeachingStaff->pluck('uuid'));

        return view('school-admin.id-cards.index', [
            'tab' => $tab,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'classStudents' => $classStudents,
            'studentSearch' => $studentSearch,
            'studentMatches' => $studentMatches,
            'studentNotice' => $studentNotice,
            'teachingStaff' => $teachingStaff,
            'nonTeachingStaff' => $nonTeachingStaff,
            'staffSearch' => $staffSearch,
            'staffMatch' => $staffMatch,
            'staffNotice' => $staffNotice,
            'cards' => IssuedIdCard::where('school_id', $school->id)->whereIn('holder_uuid', $holders->unique()->values())->get()->keyBy('holder_uuid'),
            'studentTemplates' => $school->idCardTemplates()->where('type', IdCardHolderType::Student)->get(),
            'teachingStaffTemplates' => $school->idCardTemplates()->where('type', IdCardHolderType::TeachingStaff)->get(),
            'nonTeachingStaffTemplates' => $school->idCardTemplates()->where('type', IdCardHolderType::NonTeachingStaff)->get(),
        ]);
    }

    /**
     * Issue (generate) ID cards for the ticked students or staff, without
     * printing: each gets their one card record, numbered, linked to them.
     * Someone who already has a card keeps it, number and all.
     */
    public function generate(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $this->validatedSelection($request);
        $holderType = IdCardHolderType::from($validated['type']);
        $holders = $this->holdersFrom($school, $holderType, $validated['records']);

        abort_if($holders->isEmpty(), 404);

        $before = IssuedIdCard::where('school_id', $school->id)
            ->whereIn('holder_uuid', $holders->pluck('uuid'))
            ->get()
            ->keyBy('holder_uuid');

        $template = $this->resolveTemplate($school, $holderType, $validated['template'] ?? null);

        $holders->each(fn (Student|Staff $holder) => IssuedIdCard::issueFor($school, $holderType, $holder, $template, $request->user()));

        $new = $holders->reject(fn ($holder) => $before->has($holder->uuid))->count();
        $reissued = $holders->filter(fn ($holder) => $before->get($holder->uuid)?->status === IssuedIdCardStatus::Revoked)->count();
        $kept = $holders->count() - $new - $reissued;

        AuditLog::record(
            'id-cards.generated',
            sprintf('Generated %d %s ID card(s): %d new, %d reissued, %d already issued.', $holders->count(), $holderType->label(), $new, $reissued, $kept),
            $school,
        );

        $parts = array_filter([
            $new ? "{$new} new" : null,
            $reissued ? "{$reissued} reissued with a new card number" : null,
            $kept ? "{$kept} already had a card and kept it" : null,
        ]);

        return back()->with('status', sprintf(
            '%d ID card%s ready (%s). Preview or print them below.',
            $holders->count(),
            $holders->count() === 1 ? '' : 's',
            implode(', ', $parts),
        ));
    }

    /**
     * One student by admission number, exactly (capital letters aside); a
     * number they used to have (changed since) still finds them, saying
     * so; otherwise the closest few, so a half-remembered number still
     * gets somewhere.
     *
     * @return array{0: string, 1: Collection<int, Student>, 2: ?string}
     */
    private function findStudents(School $school, string $number): array
    {
        if ($number === '') {
            return ['', collect(), null];
        }

        $exact = $school->students()->whereRaw('LOWER(admission_number) = ?', [mb_strtolower($number)])->get();

        if ($exact->isNotEmpty()) {
            return [$number, $exact, null];
        }

        $previous = PreviousIdentifier::holder($school, IdentifierGenerator::ADMISSION, $number);

        if ($previous instanceof Student) {
            return [$number, collect([$previous]), "{$number} was {$previous->fullName()}'s old admission number. It is now {$previous->admission_number}."];
        }

        $close = $school->students()->where('admission_number', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $number).'%')->orderBy('admission_number')->limit(10)->get();

        return [$number, $close, $close->isEmpty()
            ? "No student has the admission number {$number}."
            : "No student has exactly {$number}. These admission numbers contain it:"];
    }

    /**
     * One staff member by Staff ID, exactly (capital letters aside), or by
     * an old Staff ID they have since been given a new one for.
     *
     * @return array{0: string, 1: ?Staff, 2: ?string}
     */
    private function findStaff(School $school, string $staffId): array
    {
        if ($staffId === '') {
            return ['', null, null];
        }

        $match = $school->staff()->whereRaw('LOWER(staff_number) = ?', [mb_strtolower($staffId)])->first();

        if ($match) {
            return [$staffId, $match, null];
        }

        $previous = PreviousIdentifier::holder($school, IdentifierGenerator::STAFF, $staffId);

        if ($previous instanceof Staff) {
            return [$staffId, $previous, "{$staffId} was {$previous->fullName()}'s old Staff ID. It is now {$previous->staff_number}."];
        }

        return [$staffId, null, "No staff member has the Staff ID {$staffId}."];
    }

    /**
     * Renders the full preview page for a normal navigation (a graceful
     * fallback for middle-click/"open in new tab"), but when the request is
     * an in-page fetch() from the ID card preview modal ("Accept:
     * application/json"), returns the same card's front/back markup as JSON
     * instead, so the modal never has to duplicate the card's rendering.
     */
    public function preview(Request $request, string $type, string $record): View|JsonResponse
    {
        $holderType = IdCardHolderType::from($type);
        $school = $request->user()->school;

        $holder = IssuedIdCard::resolveHolder($holderType, $record, $school->id);

        abort_if(! $holder, 404);

        $template = $this->resolveTemplate($school, $holderType, $request->string('template')->toString());

        $card = IssuedIdCard::issueFor($school, $holderType, $holder, $template, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'card_number' => $card->card_number,
                // A card without a template draws in the standard design, in
                // the school's colours (App\Support\IdCardDesign), so it can
                // always be previewed, printed and downloaded.
                'has_template' => true,
                'standard_design' => ! $card->template,
                'front' => view('school-admin.id-cards._card', ['card' => $card])->render(),
                'back' => view('school-admin.id-cards._card_back', ['card' => $card])->render(),
                'holder_type' => $card->holder_type->value,
                'holder_uuid' => $card->holder_uuid,
                'template_uuid' => $card->template?->uuid,
            ]);
        }

        return view('school-admin.id-cards.preview', [
            'school' => $school,
            'card' => $card,
        ]);
    }

    /**
     * The cards, front and back, on one page: a preview to check before
     * printing, with Print and Download PDF buttons. With autoprint (the
     * preview pop-up's Print, and the Print buttons) the browser's print
     * dialog opens as the page loads.
     */
    public function print(Request $request): View
    {
        $cards = $this->issueCardsFromRequest($request);

        return view('school-admin.id-cards.print', [
            'school' => $request->user()->school,
            'cards' => $cards,
            'template' => $request->string('template')->toString(),
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $cards = $this->issueCardsFromRequest($request);

        if ($cards->count() === 1) {
            $card = $cards->first();
            $isLandscape = $card->template?->orientation === IdCardOrientation::Landscape;
            $width = ($isLandscape ? 85.6 : 53.98) * self::MM_TO_PT;
            $height = ($isLandscape ? 53.98 : 85.6) * self::MM_TO_PT;

            $pdf = Pdf::loadView('school-admin.id-cards.pdf.single', ['card' => $card])
                ->setPaper([0, 0, $width, $height]);

            return $pdf->download("id-card-{$card->card_number}.pdf");
        }

        $pdf = Pdf::loadView('school-admin.id-cards.pdf.sheet', ['cards' => $cards])->setPaper('a4');

        return $pdf->download('id-cards-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @return Collection<int, IssuedIdCard>
     */
    private function issueCardsFromRequest(Request $request): Collection
    {
        $school = $request->user()->school;
        $validated = $this->validatedSelection($request);

        $holderType = IdCardHolderType::from($validated['type']);
        $template = $this->resolveTemplate($school, $holderType, $validated['template'] ?? null);

        $cards = $this->holdersFrom($school, $holderType, $validated['records'])
            ->map(fn (Student|Staff $holder) => IssuedIdCard::issueFor($school, $holderType, $holder, $template, $request->user()))
            ->values();

        abort_if($cards->isEmpty(), 404);

        return $cards;
    }

    /**
     * @return array{type: string, records: list<string>, template?: ?string}
     */
    private function validatedSelection(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:student,teaching_staff,non_teaching_staff'],
            'records' => ['required', 'array', 'min:1', 'max:2000'],
            'records.*' => ['string'],
            'template' => ['nullable', 'string'],
        ], [
            'records.required' => 'Tick at least one person first.',
        ]);
    }

    /**
     * The school's own students or staff for these uuids, each once, in the
     * order given. Anyone not at this school is simply not found.
     *
     * @param  list<string>  $uuids
     * @return Collection<int, Student|Staff>
     */
    private function holdersFrom(School $school, IdCardHolderType $holderType, array $uuids): Collection
    {
        return collect($uuids)
            ->unique()
            ->map(fn (string $uuid) => IssuedIdCard::resolveHolder($holderType, $uuid, $school->id))
            ->filter()
            ->values();
    }

    private function resolveTemplate(School $school, IdCardHolderType $holderType, ?string $templateUuid): ?IdCardTemplate
    {
        return $school->idCardTemplates()
            ->where('type', $holderType)
            ->when(! empty($templateUuid), fn ($query) => $query->where('uuid', $templateUuid))
            ->when(empty($templateUuid), fn ($query) => $query->where('is_default', true))
            ->first();
    }
}
