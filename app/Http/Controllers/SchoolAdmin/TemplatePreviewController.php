<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Http\Controllers\Controller;
use App\Models\IdCardTemplate;
use App\Support\IdCardSample;
use App\Support\ReportCardSample;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What a School Admin's documents will look like, before they generate any.
 *
 * Both pages render the REAL templates from specimen data, the same Blade
 * files that produce a printed report card and a printed ID card. Nothing here
 * draws its own version of either, which is the only way "the preview matches
 * what is generated" can be a property of the system rather than a promise
 * somebody has to keep re-checking.
 *
 * The school is always the authenticated admin's own. It is never read from
 * the request, so there is no parameter to tamper with and no way for one
 * school to render another's crest, colours or watermark.
 */
class TemplatePreviewController extends Controller
{
    public function reportCard(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.results.template-preview', [
            'school' => $school,
            'reportCard' => ReportCardSample::for($school),
        ]);
    }

    public function idCard(Request $request): View
    {
        $school = $request->user()->school;

        $holderType = IdCardHolderType::tryFrom($request->string('holder')->toString())
            ?? IdCardHolderType::Student;

        // The school's own default template for this holder type, so the
        // preview shows the colours and instructions THIS school has saved,
        // falling back to the platform default where it has saved none.
        $template = IdCardTemplate::query()
            ->where('school_id', $school->id)
            ->where('type', $holderType)
            ->where('is_default', true)
            ->first();

        $card = IdCardSample::for(
            $school,
            $holderType,
            $template?->orientation ?? IdCardOrientation::Portrait,
            [
                'primary_color' => $template?->primary_color,
                'secondary_color' => $template?->secondary_color,
                'accent_color' => $template?->accent_color,
                'instructions' => $template?->instructions,
                'show_blood_group' => (bool) $template?->show_blood_group,
            ],
        );

        return view('school-admin.id-cards.template-preview', [
            'school' => $school,
            'holderType' => $holderType,
            'template' => $template,
            'card' => $card,
        ]);
    }
}
