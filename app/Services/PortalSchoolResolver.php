<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\GuardianLoginIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Which school somebody is signing in to, worked out from what they typed.
 *
 * The shared sign-in page on the main address has no school in its URL. Rather
 * than asking for a school code, it looks for accounts of the chosen type that
 * match the email, username, admission number, staff number, Parent ID or
 * phone number, and keeps only the ones whose password is correct.
 *
 * A School Admin's email and username are unique across the platform, so there
 * is at most one. The other identifiers are only unique within a school, so two
 * schools can each have a student "001". Checking the password is what tells
 * them apart, and when the same person genuinely has matching accounts at more
 * than one school, they are asked which school they mean.
 */
class PortalSchoolResolver
{
    /**
     * Schools where an account of this type matches the login and password.
     *
     * @return Collection<int, School>
     */
    public function schoolsFor(string $guard, string $login, string $password): Collection
    {
        $login = trim($login);

        $accounts = $login === '' || $password === '' ? collect() : $this->candidates($guard, $login);

        if ($accounts->isEmpty()) {
            // The same work whether or not the login exists, so the response
            // time does not reveal which logins are real.
            Hash::check($password, '$2y$12$'.str_repeat('a', 53));

            return new Collection;
        }

        $schoolIds = $accounts
            ->filter(fn ($account) => filled($account->getAuthPassword()) && Hash::check($password, $account->getAuthPassword()))
            ->pluck('school_id')
            ->filter()
            ->unique()
            ->values();

        return School::query()->whereIn('id', $schoolIds)->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User|Staff|Student|Guardian>
     */
    private function candidates(string $guard, string $login): \Illuminate\Support\Collection
    {
        $isEmail = str_contains($login, '@');

        return match ($guard) {
            'web' => User::query()
                ->where('role', UserRole::SchoolAdmin)
                ->whereNotNull('school_id')
                ->where($isEmail ? 'email' : 'username', $login)
                ->get(),
            'staff' => Staff::query()->where($isEmail ? 'email' : 'staff_number', $login)->get(),
            'student' => Student::query()->where($isEmail ? 'email' : 'admission_number', $login)->get(),
            'guardian' => $isEmail ? collect() : $this->guardians($login),
            default => collect(),
        };
    }

    /**
     * Parents sign in with a Parent ID or a phone number. A phone number can
     * be written many ways, so it is compared after normalising, as the
     * school-scoped sign-in does.
     *
     * @return \Illuminate\Support\Collection<int, Guardian>
     */
    private function guardians(string $login): \Illuminate\Support\Collection
    {
        $byNumber = Guardian::query()
            ->whereRaw('LOWER(guardian_number) = ?', [mb_strtolower($login)])
            ->get();

        $phone = GuardianLoginIdentifier::normalisePhone($login);

        if ($phone === null) {
            return $byNumber;
        }

        $byPhone = Guardian::query()
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->when($byNumber->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $byNumber->modelKeys()))
            ->lazyById(500)
            ->filter(fn (Guardian $guardian) => GuardianLoginIdentifier::normalisePhone((string) $guardian->phone) === $phone)
            ->values()
            ->collect();

        return $byNumber->toBase()->merge($byPhone);
    }
}
