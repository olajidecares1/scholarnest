<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\EventAudience;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\SchoolEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;
        $when = $request->string('when', 'upcoming')->toString();

        $events = $school->events()
            ->when($when === 'upcoming', fn ($query) => $query->where('starts_at', '>=', now())->orderBy('starts_at'))
            ->when($when === 'past', fn ($query) => $query->where('starts_at', '<', now())->orderByDesc('starts_at'))
            ->when($when === 'all', fn ($query) => $query->orderBy('starts_at'))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.events.index', [
            'website' => $school->website,
            'events' => $events,
            'when' => $when,
            'upcomingCount' => $school->events()->where('starts_at', '>=', now())->count(),
            'audienceOptions' => EventAudience::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $request->merge(['is_all_day' => $request->boolean('is_all_day')]);
        $validated = $request->validate($this->rules());

        $event = $school->events()->create($validated);

        return back()->with('status', "{$event->title} was added to the calendar.");
    }

    public function update(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $request->merge(['is_all_day' => $request->boolean('is_all_day')]);
        $validated = $request->validate($this->rules());
        $event->update($validated);

        return back()->with('status', "{$event->title} was updated.");
    }

    public function destroy(SchoolEvent $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $title = $event->title;
        $event->delete();

        return back()->with('status', "{$title} was removed from the calendar.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(EventAudience::class)],
            'is_all_day' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    private function authorizeEvent(SchoolEvent $event): void
    {
        $this->authorizeSchoolOwnership($event);
    }
}
