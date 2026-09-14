<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterSchoolRequest;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SchoolRegisteredNotification;
use App\Services\DefaultAcademicStructure;
use App\Services\TeamNotifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly TeamNotifier $team) {}

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'background' => Setting::current()->registerBackgroundMedia,
        ]);
    }

    /**
     * Handle an incoming school registration request.
     */
    public function store(RegisterSchoolRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $school = School::create([
                'name' => $validated['school_name'],

                // The registration form is the only place these are collected,
                // so they are kept as the school's billing contact rather than
                // asked for a second time later.
                'billing_email' => $validated['email'],
                'billing_phone' => $validated['phone'],
            ]);

            DefaultAcademicStructure::seedFor($school);

            return User::create([
                'name' => $validated['school_name'],
                'email' => $validated['email'],
                'username' => User::generateUniqueUsernameFromEmail($validated['email']),
                'password' => Hash::make($validated['password']),
                'role' => UserRole::SchoolAdmin,
                'school_id' => $school->id,
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        AuditLog::record('school.registered', "New school \"{$user->school->name}\" registered and is awaiting a subscription.", $user->school);

        // One registration, one notification. The key is the school's, not
        // this request's, so a retry or a re-delivered job announces nothing
        // twice, see App\Services\TeamNotifier.
        $this->team->once(
            SchoolRegisteredNotification::eventKeyFor($user->school),
            new SchoolRegisteredNotification($user->school),
        );

        return redirect(route('dashboard', absolute: false));
    }
}
