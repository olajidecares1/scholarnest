<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\IdCardTemplate;
use App\Services\ImageOptimizer;
use App\Support\StoredUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdCardTemplateController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.id-cards.templates', [
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
            'instructions' => ['nullable', 'string', 'max:2000'],
            'show_blood_group' => ['nullable', 'boolean'],
            'show_dob' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'background' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function storeBackground(Request $request): ?string
    {
        if (! $request->hasFile('background')) {
            return null;
        }

        $file = $request->file('background');
        $path = $file->storeAs('id-card-templates', StoredUpload::name($file), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    private function authorizeTemplate(IdCardTemplate $template): void
    {
        $this->authorizeSchoolOwnership($template);
    }
}
