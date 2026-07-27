<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with('school');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $users = $query->latest()->paginate(10)->withQueryString();

        return view('super-admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('super-admin.users.create', [
            'schools' => School::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::SchoolAdmin,
            'school_id' => $validated['school_id'],
        ]);

        AuditLog::record('user.created', "Added school admin {$user->name}.", $user);

        return redirect()->route('super-admin.users.index')
            ->with('status', "{$user->name} has been added as a school admin.");
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['is_active' => true]);

        AuditLog::record('user.activated', "Activated user {$user->name}.", $user);

        return back()->with('status', "{$user->name} has been activated.");
    }

    public function deactivate(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => false]);

        AuditLog::record('user.deactivated', "Deactivated user {$user->name}.", $user);

        return back()->with('status', "{$user->name} has been deactivated.");
    }
}
