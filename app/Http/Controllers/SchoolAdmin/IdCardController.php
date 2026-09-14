<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\IdCardTemplate;
use App\Models\IssuedIdCard;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class IdCardController extends Controller
{
    private const MM_TO_PT = 2.83464567;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.id-cards.index', [
            'students' => $school->students()->orderBy('first_name')->get(),
            'teachingStaff' => $school->staff()->where('role', StaffRole::Teacher)->orderBy('first_name')->get(),
            'nonTeachingStaff' => $school->staff()->where('role', '!=', StaffRole::Teacher)->orderBy('first_name')->get(),
            'studentTemplates' => $school->idCardTemplates()->where('type', IdCardHolderType::Student)->get(),
            'teachingStaffTemplates' => $school->idCardTemplates()->where('type', IdCardHolderType::TeachingStaff)->get(),
            'nonTeachingStaffTemplates' => $school->idCardTemplates()->where('type', IdCardHolderType::NonTeachingStaff)->get(),
        ]);
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
                'has_template' => (bool) $card->template,
                'front' => $card->template ? view('school-admin.id-cards._card', ['card' => $card])->render() : null,
                'back' => $card->template ? view('school-admin.id-cards._card_back', ['card' => $card])->render() : null,
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

    public function print(Request $request): View
    {
        $cards = $this->issueCardsFromRequest($request);

        return view('school-admin.id-cards.print', [
            'school' => $request->user()->school,
            'cards' => $cards,
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

        $validated = $request->validate([
            'type' => ['required', 'in:student,teaching_staff,non_teaching_staff'],
            'records' => ['required', 'array', 'min:1'],
            'records.*' => ['string'],
            'template' => ['nullable', 'string'],
        ]);

        $holderType = IdCardHolderType::from($validated['type']);
        $template = $this->resolveTemplate($school, $holderType, $validated['template'] ?? null);

        $cards = collect($validated['records'])
            ->map(fn (string $uuid) => IssuedIdCard::resolveHolder($holderType, $uuid, $school->id))
            ->filter()
            ->map(fn (Student|Staff $holder) => IssuedIdCard::issueFor($school, $holderType, $holder, $template, $request->user()))
            ->values();

        abort_if($cards->isEmpty(), 404);

        return $cards;
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
