<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransportController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.transport.index', [
            'vehicles' => $school->transportVehicles()->withCount('routes')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->vehicleRules());

        $vehicle = $school->transportVehicles()->create($validated);

        return back()->with('status', "{$vehicle->name} was added to the fleet.");
    }

    public function update(Request $request, TransportVehicle $vehicle): RedirectResponse
    {
        $this->authorizeVehicle($vehicle);

        $validated = $request->validate($this->vehicleRules());
        $vehicle->update($validated);

        return back()->with('status', "{$vehicle->name} was updated.");
    }

    public function destroy(TransportVehicle $vehicle): RedirectResponse
    {
        $this->authorizeVehicle($vehicle);

        if ($vehicle->routes()->exists()) {
            return back()->with('status', "{$vehicle->name} is assigned to a route and cannot be removed.");
        }

        $name = $vehicle->name;
        $vehicle->delete();

        return back()->with('status', "{$name} was removed from the fleet.");
    }

    public function routes(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.transport.routes', [
            'routes' => $school->transportRoutes()->with('vehicle')->withCount('assignments')->orderBy('name')->get(),
            'vehicles' => $school->transportVehicles()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->routeRules($school->id));

        $route = $school->transportRoutes()->create($validated);

        return back()->with('status', "{$route->name} was created.");
    }

    public function updateRoute(Request $request, TransportRoute $route): RedirectResponse
    {
        $this->authorizeRoute($route);

        $validated = $request->validate($this->routeRules($route->school_id));
        $route->update($validated);

        return back()->with('status', "{$route->name} was updated.");
    }

    public function destroyRoute(TransportRoute $route): RedirectResponse
    {
        $this->authorizeRoute($route);

        $name = $route->name;
        $route->delete();

        return redirect()->route('transport.routes.index')->with('status', "{$name} was deleted.");
    }

    public function students(TransportRoute $route): View
    {
        $this->authorizeRoute($route);

        return view('school-admin.transport.students', [
            'route' => $route,
            'assignments' => $route->assignments()->with('student')->get(),
            'students' => Student::where('school_id', $route->school_id)
                ->where('is_active', true)
                ->whereDoesntHave('transportAssignment')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function storeAssignment(Request $request, TransportRoute $route): RedirectResponse
    {
        $this->authorizeRoute($route);

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'pickup_point' => ['nullable', 'string', 'max:150'],
        ]);

        $student = Student::where('school_id', $route->school_id)->find($validated['student_id']);

        if (! $student) {
            return back()->with('status', 'Could not find that student.');
        }

        TransportAssignment::updateOrCreate(
            ['student_id' => $student->id],
            ['transport_route_id' => $route->id, 'pickup_point' => $validated['pickup_point'] ?? null],
        );

        return back()->with('status', "{$student->fullName()} was assigned to {$route->name}.");
    }

    public function destroyAssignment(TransportAssignment $assignment): RedirectResponse
    {
        $this->authorizeSchoolOwnership($assignment->route);

        $route = $assignment->route;
        $assignment->delete();

        return redirect()->route('transport.routes.students', $route)->with('status', 'Student was removed from the route.');
    }

    /**
     * @return array<string, mixed>
     */
    private function vehicleRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'plate_number' => ['nullable', 'string', 'max:30'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeRules(int $schoolId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'transport_vehicle_id' => ['nullable', 'integer', Rule::exists('transport_vehicles', 'id')->where('school_id', $schoolId)],
        ];
    }

    private function authorizeVehicle(TransportVehicle $vehicle): void
    {
        $this->authorizeSchoolOwnership($vehicle);
    }

    private function authorizeRoute(TransportRoute $route): void
    {
        $this->authorizeSchoolOwnership($route);
    }
}
