<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterSchoolRequest;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
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

        return redirect(route('dashboard', absolute: false));
    }
}
