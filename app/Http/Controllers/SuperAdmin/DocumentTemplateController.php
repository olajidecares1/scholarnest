<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\IdCardSample;
use App\Support\ReportCardSample;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The AkademicNest Team's view of the documents schools hand out.
 *
 * Both previews render the REAL templates, the same Blade files that produce
 * a printed report card and a printed ID card, from specimen data. Nothing
 * here draws its own version of either design, because a preview that is a
 * separate drawing is a preview that will eventually disagree with what
 * schools receive. That already happened once, in the ID card editor, and is
 * why the samples exist as shared classes rather than as markup in a page.
 *
 * A school may be chosen so the Team can see any school's real crest, colours,
 * watermark and contact details flowing through the same templates. With none
 * chosen it falls back to whichever school comes first, and to a plain unsaved
 * School when the platform has none at all, a brand-new installation should
 * still be able to look at its own default design.
 */
class DocumentTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $schools = School::query()->orderBy('name')->get(['id', 'uuid', 'name']);

        $school = $this->resolveSchool($request, $schools);

        $holderType = IdCardHolderType::tryFrom($request->string('holder')->toString())
            ?? IdCardHolderType::Student;

        return view('super-admin.document-templates.index', [
            'schools' => $schools,
            'school' => $school,
            'holderType' => $holderType,

            // The report card, built exactly as a real one is, see
            // ReportCardData::for(), whose shape this mirrors.
            'reportCard' => ReportCardSample::for($school),

            'idCard' => IdCardSample::for($school, $holderType, IdCardOrientation::Portrait),
        ]);
    }

    /**
     * @param  Collection<int, School>  $schools
     */
    private function resolveSchool(Request $request, $schools): School
    {
        $uuid = $request->string('school')->toString();

        if ($uuid !== '') {
            $chosen = School::query()->where('uuid', $uuid)->first();

            if ($chosen) {
                return $chosen;
            }
        }

        $first = $schools->first();

        if ($first) {
            return School::query()->find($first->id);
        }

        // No schools registered yet. An unsaved School still carries the
        // default design, which is the thing being previewed.
        $placeholder = new School([
            'name' => 'Sample School',
            'current_session' => now()->year.'/'.(now()->year + 1),
        ]);

        $placeholder->id = 0;

        return $placeholder;
    }
}
