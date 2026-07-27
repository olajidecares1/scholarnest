<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSchoolRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $query = School::query()->with(['activeSubscription.plan', 'users']);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('is_active', $status === 'active');
        }

        $schools = $query->latest()->paginate(10)->withQueryString();

        return view('super-admin.schools.index', [
            'schools' => $schools,
        ]);
    }

    public function create(): View
    {
        return view('super-admin.schools.create');
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $school = DB::transaction(function () use ($validated) {
            $school = School::create([
                'name' => $validated['school_name'],
            ]);

            User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::SchoolAdmin,
                'school_id' => $school->id,
            ]);

            return $school;
        });

        return redirect()->route('super-admin.schools.show', $school)
            ->with('status', "{$school->name} has been added with an admin account.");
    }

    public function show(School $school): View
    {
        $school->load(['users', 'subscriptions' => fn ($query) => $query->with('plan')->latest()]);

        return view('super-admin.schools.show', [
            'school' => $school,
        ]);
    }

    public function activate(School $school): RedirectResponse
    {
        $school->update(['is_active' => true, 'deactivated_at' => null]);

        return back()->with('status', "{$school->name} has been activated.");
    }

    public function deactivate(School $school): RedirectResponse
    {
        $school->update(['is_active' => false, 'deactivated_at' => now()]);

        return back()->with('status', "{$school->name} has been deactivated.");
    }
}
