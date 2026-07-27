<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\CbtSubjectCategory;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CbtSubject;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CbtSubjectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueSlugRule()],
            'category' => ['required', Rule::enum(CbtSubjectCategory::class)],
        ]);

        $subject = CbtSubject::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'category' => $validated['category'],
        ]);

        AuditLog::record('cbt.subject.created', "Created CBT subject \"{$subject->name}\".", $subject);

        return back()->with('status', "\"{$subject->name}\" added successfully.");
    }

    public function update(Request $request, CbtSubject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueSlugRule($subject)],
            'category' => ['required', Rule::enum(CbtSubjectCategory::class)],
        ]);

        $subject->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'category' => $validated['category'],
        ]);

        AuditLog::record('cbt.subject.updated', "Updated CBT subject \"{$subject->name}\".", $subject);

        return back()->with('status', "\"{$subject->name}\" updated successfully.");
    }

    public function destroy(CbtSubject $subject): RedirectResponse
    {
        $name = $subject->name;
        $subject->delete();

        AuditLog::record('cbt.subject.deleted', "Deleted CBT subject \"{$name}\".");

        return back()->with('status', "\"{$name}\" deleted successfully.");
    }

    private function uniqueSlugRule(?CbtSubject $ignoring = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoring) {
            $exists = CbtSubject::where('slug', Str::slug((string) $value))
                ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->id))
                ->exists();

            if ($exists) {
                $fail('A subject with this name already exists.');
            }
        };
    }
}
