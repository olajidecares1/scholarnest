<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\IdCardTemplate;
use App\Rules\UploadedImage;
use App\Services\Uploads\UploadStorage;
use App\Support\IdCardSample;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdCardTemplateController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(private readonly UploadStorage $uploads) {}

    /**
     * The specimen card shown beside the editor.
     *
     * Rendered by the real card templates from invented details, so what a
     * School Admin approves here is the card that prints. The editor asks for
     * this again whenever a colour or the card type changes, which is why the
     * colours arrive as query parameters rather than being read from a saved
     * template - nothing has been saved yet.
     *
     * Nothing is written: IdCardSample builds unsaved models.
     */
    public function sample(Request $request): JsonResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(IdCardHolderType::class)],
            'orientation' => ['nullable', Rule::enum(IdCardOrientation::class)],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'show_blood_group' => ['nullable', 'boolean'],
        ]);

        $card = IdCardSample::for(
            $school,
            IdCardHolderType::tryFrom($validated['type'] ?? '') ?? IdCardHolderType::Student,
            IdCardOrientation::tryFrom($validated['orientation'] ?? '') ?? IdCardOrientation::Portrait,
            [
                'primary_color' => $validated['primary_color'] ?? null,
                'secondary_color' => $validated['secondary_color'] ?? null,
                'accent_color' => $validated['accent_color'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'show_blood_group' => (bool) ($validated['show_blood_group'] ?? false),
            ],
        );

        return response()->json([
            'front' => view('school-admin.id-cards._card', ['card' => $card])->render(),
            'back' => view('school-admin.id-cards._card_back', ['card' => $card])->render(),
        ]);
    }

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.id-cards.templates', [
            'school' => $school,
            'templates' => $school->idCardTemplates()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $template = $school->idCardTemplates()->create([
            ...collect($validated)->except(['background', 'show_blood_group', 'show_dob', 'is_default'])->all(),
            'show_blood_group' => $request->boolean('show_blood_group'),
            'show_dob' => $request->boolean('show_dob'),
            'is_default' => $request->boolean('is_default'),
            'background_path' => $this->storeBackground($request),
        ]);

        return back()->with('status', "\"{$template->name}\" was added.");
    }

    public function update(Request $request, IdCardTemplate $template): RedirectResponse
    {
        $this->authorizeTemplate($template);

        $validated = $request->validate($this->rules());
        $backgroundPath = $this->storeBackground($request);

        $template->update([
            ...collect($validated)->except(['background', 'show_blood_group', 'show_dob', 'is_default'])->all(),
            'show_blood_group' => $request->boolean('show_blood_group'),
            'show_dob' => $request->boolean('show_dob'),
            'is_default' => $request->boolean('is_default'),
            'background_path' => $backgroundPath ?: $template->background_path,
        ]);

        return back()->with('status', "\"{$template->name}\" was updated.");
    }

    public function destroy(IdCardTemplate $template): RedirectResponse
    {
        $this->authorizeTemplate($template);

        if ($template->background_path) {
            Storage::disk('public')->delete($template->background_path);
        }

        $name = $template->name;
        $template->delete();

        return back()->with('status', "\"{$name}\" was removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(IdCardHolderType::class)],
            'orientation' => ['required', Rule::enum(IdCardOrientation::class)],
            'primary_color' => ['required', 'string', 'max:20'],
            'secondary_color' => ['required', 'string', 'max:20'],
            // Nullable: a template saved before this colour existed keeps the
            // reference red until its school chooses another.
            'accent_color' => ['nullable', 'string', 'max:20'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'show_blood_group' => ['nullable', 'boolean'],
            'show_dob' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'background' => UploadedImage::rules(ImageProfile::Website),
        ];
    }

    private function storeBackground(Request $request): ?string
    {
        if (! $request->hasFile('background')) {
            return null;
        }

        return $this->uploads->storeImage($request->file('background'), 'public', 'id-card-templates', ImageProfile::Website, 'background')->path;
    }

    private function authorizeTemplate(IdCardTemplate $template): void
    {
        $this->authorizeSchoolOwnership($template);
    }
}
