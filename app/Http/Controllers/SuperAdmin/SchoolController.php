<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSchoolRequest;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\StudentLicenceAllocation;
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

            DefaultAcademicStructure::seedFor($school);

            User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'username' => User::generateUniqueUsernameFromEmail($validated['admin_email']),
                'password' => Hash::make($validated['password']),
                'role' => UserRole::SchoolAdmin,
                'school_id' => $school->id,
            ]);

            return $school;
        });

        AuditLog::record('school.created', "Added school {$school->name}.", $school);

        return redirect()->route('super-admin.schools.show', $school)
            ->with('status', "{$school->name} has been added with an admin account.");
    }

    public function show(School $school, StudentLicenceAllocation $licences): View
    {
        $school->load(['users', 'subscriptions' => fn ($query) => $query->with('plan')->latest()]);

        return view('super-admin.schools.show', [
            'school' => $school,

            // Every request ever made against this school, in the order they
            // happened. These are the history behind the single cumulative
            // figure below - not separate allowances the school can draw on.
            'topUps' => $licences->requestHistory($school),

            // Read through the same service the enforcement uses, and the same
            // one the school's own dashboard reads, so neither screen can
            // drift from the figure that actually admits or refuses a student.
            // Null means the plan is not sold per student.
            'capacity' => $licences->summary($school),

            // Every invoice AkademicNest has raised against this school, and
            // through each one its payment and who approved it. Eager-loaded
            // because the table reads the payment and the approver on every
            // row, and without this a school with a long history would issue
            // a query per row to render it.
            'invoices' => $school->subscriptionInvoices()
                ->with([
                    'subscription.latestPayment.verifiedBy',
                    'topUp.verifiedBy',
                ])
                ->latest('issued_at')
                ->get(),
        ]);
    }

    public function activate(School $school): RedirectResponse
    {
        $school->update(['is_active' => true, 'deactivated_at' => null]);

        AuditLog::record('school.activated', "Activated school {$school->name}.", $school);

        return back()->with('status', "{$school->name} has been activated.");
    }

    public function deactivate(School $school): RedirectResponse
    {
        $school->update(['is_active' => false, 'deactivated_at' => now()]);

        AuditLog::record('school.deactivated', "Deactivated school {$school->name}.", $school);

        return back()->with('status', "{$school->name} has been deactivated.");
    }

    /**
     * Delete a school and everything belonging to it.
     *
     * Irreversible, and far wider than it looks: `schools` is the parent of 43
     * cascading foreign keys, so this removes the school's students, staff,
     * guardians, attendance, examinations, results, result tokens, invoices,
     * website and support tickets along with it. Deactivating is the
     * reversible alternative, and is what the interface offers first.
     *
     * The Super Admin has to type the school's name to confirm. That is not
     * ceremony: the delete button sits in a list of similar rows, and the name
     * is the one thing that cannot be got right by clicking the wrong one.
     */
    public function destroy(Request $request, School $school): RedirectResponse
    {
        $request->validate([
            'confirm_name' => ['required', 'string'],
        ], [
            'confirm_name.required' => 'Type the school name to confirm deletion.',
        ]);

        if (trim($request->string('confirm_name')->toString()) !== $school->name) {
            return back()->withErrors([
                'confirm_name' => 'That name does not match. Nothing was deleted.',
            ]);
        }

        // Counted before the delete, because afterwards there is nothing left
        // to count - and the audit entry is the only record that any of it
        // existed.
        $summary = sprintf(
            '%d student(s), %d staff, %d subscription(s), %d admin account(s)',
            $school->students()->count(),
            $school->staff()->count(),
            $school->subscriptions()->count(),
            $school->users()->count(),
        );

        $name = $school->name;
        $slug = $school->slug;

        // Recorded first: AuditLog stores a subject_type/subject_id pointing at
        // the school, and writing it afterwards would leave a reference to a
        // row that no longer exists.
        AuditLog::record(
            'school.deleted',
            "Deleted school {$name} ({$slug}), along with {$summary}.",
            $school,
        );

        // One statement, because the rule is no longer this controller's to
        // remember. users.school_id cascades, so the school's accounts go with
        // it however it is removed, and the model clears the sign-in residue
        // that no foreign key can reach.
        //
        // This used to delete the accounts by hand first, because the
        // constraint was SET NULL and would otherwise cut them loose - leaving
        // an admin who could not sign in but still held the school's email
        // address, which is what stopped a deleted school ever registering
        // again. A rule kept in one controller only holds on the one path that
        // remembers it.
        DB::transaction(fn () => $school->delete());

        return redirect()
            ->route('super-admin.schools.index')
            ->with('status', "{$name} and all of its records were permanently deleted.");
    }
}
