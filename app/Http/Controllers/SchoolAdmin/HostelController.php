<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\HostelGender;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HostelController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.hostels.index', [
            'hostels' => $school->hostels()->withCount('rooms')->orderBy('name')->get(),
            'genderOptions' => HostelGender::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->hostelRules());

        $hostel = $school->hostels()->create($validated);

        return back()->with('status', "{$hostel->name} was added.");
    }

    public function update(Request $request, Hostel $hostel): RedirectResponse
    {
        $this->authorizeHostel($hostel);

        $validated = $request->validate($this->hostelRules());
        $hostel->update($validated);

        return back()->with('status', "{$hostel->name} was updated.");
    }

    public function destroy(Hostel $hostel): RedirectResponse
    {
        $this->authorizeHostel($hostel);

        if ($hostel->rooms()->exists()) {
            return back()->with('status', "{$hostel->name} has rooms and cannot be removed until they're deleted.");
        }

        $name = $hostel->name;
        $hostel->delete();

        return back()->with('status', "{$name} was removed.");
    }

    public function rooms(Hostel $hostel): View
    {
        $this->authorizeHostel($hostel);

        return view('school-admin.hostels.rooms', [
            'hostel' => $hostel,
            'rooms' => $hostel->rooms()->withCount('allocations')->orderBy('room_number')->get(),
        ]);
    }

    public function storeRoom(Request $request, Hostel $hostel): RedirectResponse
    {
        $this->authorizeHostel($hostel);

        $validated = $request->validate([
            'room_number' => [
                'required', 'string', 'max:20',
                Rule::unique('hostel_rooms', 'room_number')->where('hostel_id', $hostel->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $hostel->rooms()->create($validated);

        return back()->with('status', "Room {$validated['room_number']} was added.");
    }

    public function updateRoom(Request $request, HostelRoom $room): RedirectResponse
    {
        $this->authorizeRoom($room);

        $validated = $request->validate([
            'room_number' => [
                'required', 'string', 'max:20',
                Rule::unique('hostel_rooms', 'room_number')->where('hostel_id', $room->hostel_id)->ignore($room->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $room->update($validated);

        return back()->with('status', "Room {$room->room_number} was updated.");
    }

    public function destroyRoom(HostelRoom $room): RedirectResponse
    {
        $this->authorizeRoom($room);

        if ($room->allocations()->exists()) {
            return back()->with('status', "Room {$room->room_number} has students allocated and cannot be removed.");
        }

        $number = $room->room_number;
        $hostel = $room->hostel;
        $room->delete();

        return redirect()->route('hostels.rooms', $hostel)->with('status', "Room {$number} was removed.");
    }

    public function students(HostelRoom $room): View
    {
        $this->authorizeRoom($room);

        return view('school-admin.hostels.students', [
            'room' => $room,
            'allocations' => $room->allocations()->with('student')->get(),
            'students' => Student::where('school_id', $room->hostel->school_id)
                ->where('is_active', true)
                ->whereDoesntHave('hostelAllocation')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function storeAllocation(Request $request, HostelRoom $room): RedirectResponse
    {
        $this->authorizeRoom($room);

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $student = Student::where('school_id', $room->hostel->school_id)->find($validated['student_id']);

        if (! $student) {
            return back()->with('status', 'Could not find that student.');
        }

        if ($room->occupancy() >= $room->capacity) {
            return back()->with('status', "Room {$room->room_number} is already at full capacity.");
        }

        HostelAllocation::updateOrCreate(
            ['student_id' => $student->id],
            ['hostel_room_id' => $room->id, 'allocated_date' => today()],
        );

        return back()->with('status', "{$student->fullName()} was allocated to Room {$room->room_number}.");
    }

    public function destroyAllocation(HostelAllocation $allocation): RedirectResponse
    {
        $this->authorizeSchoolOwnership($allocation->room->hostel);

        $room = $allocation->room;
        $allocation->delete();

        return redirect()->route('hostels.students', $room)->with('status', 'Student was vacated from the room.');
    }

    /**
     * @return array<string, mixed>
     */
    private function hostelRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'gender' => ['required', Rule::enum(HostelGender::class)],
            'warden_name' => ['nullable', 'string', 'max:150'],
            'warden_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    private function authorizeHostel(Hostel $hostel): void
    {
        $this->authorizeSchoolOwnership($hostel);
    }

    private function authorizeRoom(HostelRoom $room): void
    {
        $this->authorizeSchoolOwnership($room->hostel);
    }
}
