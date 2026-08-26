<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreAdminRoleRequest;
use App\Http\Requests\SuperAdmin\StoreTeamMemberRequest;
use App\Models\AdminRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('super-admin.roles.index', [
            'roles' => AdminRole::withCount('users')->orderBy('name')->get(),
            'teamMembers' => User::where('role', UserRole::SuperAdmin)->with('adminRole')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.roles.create', [
            'permissions' => AdminRole::PERMISSIONS,
        ]);
    }

    public function store(StoreAdminRoleRequest $request): RedirectResponse
    {
        $role = AdminRole::create($request->validated());

        AuditLog::record('role.created', "Created role {$role->name}.", $role);

        return redirect()->route('super-admin.roles.index')->with('status', 'Role created successfully.');
    }

    public function edit(AdminRole $role): View
    {
        return view('super-admin.roles.edit', [
            'role' => $role,
            'permissions' => AdminRole::PERMISSIONS,
        ]);
    }

    public function update(StoreAdminRoleRequest $request, AdminRole $role): RedirectResponse
    {
        $role->update($request->validated());

        AuditLog::record('role.updated', "Updated role {$role->name}.", $role);

        return redirect()->route('super-admin.roles.index')->with('status', 'Role updated successfully.');
    }

    public function destroy(AdminRole $role): RedirectResponse
    {
        $roleName = $role->name;
        $role->users()->update(['admin_role_id' => null]);
        $role->delete();

        AuditLog::record('role.deleted', "Deleted role {$roleName}.");

        return back()->with('status', 'Role deleted. Any team members using it now have full access.');
    }

    public function createTeamMember(): View
    {
        return view('super-admin.roles.team-create', [
            'roles' => AdminRole::orderBy('name')->get(),
        ]);
    }

    public function storeTeamMember(StoreTeamMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $member = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => User::generateUniqueUsernameFromEmail($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => UserRole::SuperAdmin,
            'admin_role_id' => $validated['admin_role_id'] ?? null,
        ]);

        AuditLog::record('team.added', "Added EduNest Team team member {$member->name}.", $member);

        return redirect()->route('super-admin.roles.index')->with('status', 'Team member added successfully.');
    }
}
