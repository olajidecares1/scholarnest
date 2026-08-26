<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.testimonials.index', [
            'testimonials' => $school->testimonials()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $testimonial = $school->testimonials()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $school->testimonials()->max('sort_order') + 1,
        ]);

        return back()->with('status', "Testimonial from {$testimonial->name} was added.");
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($testimonial);

        $validated = $request->validate($this->rules());

        $testimonial->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', "Testimonial from {$testimonial->name} was updated.");
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($testimonial);

        $testimonial->delete();

        return back()->with('status', 'Testimonial removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:150'],
            'quote' => ['required', 'string', 'max:1000'],
        ];
    }

    private function authorizeTestimonial(Testimonial $testimonial): void
    {
        $this->authorizeSchoolOwnership($testimonial);
    }
}
