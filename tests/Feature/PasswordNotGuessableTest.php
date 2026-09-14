<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * A password must not be made out of the account it protects.
 *
 * Password::defaults() already demands 8 characters, mixed case, a number and
 * a symbol, and "Greenfield2026!" clears every one of them while being the
 * first thing anybody would type against Greenfield College. Complexity rules
 * measure a password's SHAPE; they say nothing about how guessable it is, and
 * on a platform where the school's name and email sit at the top of its own
 * website, those are the two most guessable ingredients there are.
 */
function passwordPasses(string $password, array $identity): bool
{
    return Validator::make(
        ['password' => $password],
        ['password' => [new NotDerivedFromIdentity($identity)]],
    )->passes();
}

describe('what the rule refuses', function () {
    test('the school name, however it is dressed up', function () {
        $identity = ['Greenfield College', 'admin@greenfield.example'];

        expect(passwordPasses('Greenfield2026!', $identity))->toBeFalse()
            ->and(passwordPasses('greenfield!A1', $identity))->toBeFalse()
            ->and(passwordPasses('GREENFIELD@99x', $identity))->toBeFalse()

            // Spacing and punctuation are flattened before comparing, so
            // breaking the word up does not get past it.
            ->and(passwordPasses('Green-Field.2026!', $identity))->toBeFalse();
    });

    test('the school name with digits standing in for letters', function () {
        // The substitution people actually reach for when a rule rejects the
        // plain word. It is no harder to guess, so it is refused too.
        expect(passwordPasses('Gr33nf13ld!2026', ['Greenfield College']))->toBeFalse()
            ->and(passwordPasses('Gr33nfi3ld@1', ['Greenfield College']))->toBeFalse();
    });

    test('either word of a two-word school name, not only the whole phrase', function () {
        $identity = ['Greenfield International College'];

        expect(passwordPasses('Greenfield#12', $identity))->toBeFalse()
            ->and(passwordPasses('International#12', $identity))->toBeFalse()

            // "College" too. Every customer here is a school, an academy or a
            // college, so the generic half is exactly the guessable half.
            ->and(passwordPasses('College#2026', $identity))->toBeFalse();
    });

    test('the email address, whole or in pieces', function () {
        $identity = ['bursar@greenfieldcollege.com'];

        expect(passwordPasses('bursar@Greenfieldcollege.com1', $identity))->toBeFalse()

            // The part before the @ is what people reuse.
            ->and(passwordPasses('Bursar2026!', $identity))->toBeFalse()

            // And the domain's own name is the school's name again.
            ->and(passwordPasses('Greenfieldcollege!1', $identity))->toBeFalse();
    });

    test('a password that is merely a slice of the identifier', function () {
        // Caught in both directions: the identifier inside the password is the
        // common case, and this is the other one, a password cut out of the
        // middle of the school's own name.
        expect(passwordPasses('fieldcoll', ['Greenfield College']))->toBeFalse();
    });
});

describe('what the rule allows', function () {
    test('an unrelated password of the same shape', function () {
        expect(passwordPasses('Torrent-Vault-88!', ['Greenfield College', 'admin@greenfield.example']))->toBeTrue();
    });

    test('short accidental overlaps do not fire', function () {
        // A school called "Ark" must not refuse every password containing
        // "dark" or "marked". A rule that rejects innocent passwords teaches
        // people to work around it rather than to choose a better one.
        expect(passwordPasses('Darkened-Sky-42!', ['Ark Academy']))->toBeTrue();
    });

    test('an empty identity list refuses nothing', function () {
        expect(passwordPasses('Anything-At-All-9!', [null, '']))->toBeTrue();
    });
});

describe('where it applies', function () {
    beforeEach(function () {
        $this->school = activateSchool(
            School::factory()->create(['name' => 'Greenfield College']),
            PlanKey::Standard,
        );

        $this->admin = User::factory()->create([
            'role' => UserRole::SchoolAdmin,
            'school_id' => $this->school->id,
            'name' => 'Grace Adeyemi',
            'email' => 'grace@greenfield.example',
        ]);
    });

    test('registering a school', function () {
        $this->post(route('register'), [
            'school_name' => 'Sunrise Academy',
            'email' => 'head@sunrise.example',
            'phone' => '08030000000',
            'password' => 'Sunrise2026!',
            'password_confirmation' => 'Sunrise2026!',
            'terms' => 'on',
        ])->assertSessionHasErrors('password');

        expect(School::where('name', 'Sunrise Academy')->exists())->toBeFalse();
    });

    test('a School Admin issuing a teacher their login details', function () {
        $member = Staff::factory()->create([
            'school_id' => $this->school->id,
            'role' => StaffRole::Teacher,
            'staff_number' => 'STF-9001',
        ]);

        $before = $member->password;

        $this->actingAs($this->admin)
            ->put(route('staff.credentials', $member), [
                'password' => 'Greenfield2026!',
                'password_confirmation' => 'Greenfield2026!',
            ])
            ->assertSessionHasErrors('password');

        expect($member->fresh()->password)->toBe($before);
    });

    test('and the same teacher with an unrelated password is accepted', function () {
        $member = Staff::factory()->create([
            'school_id' => $this->school->id,
            'role' => StaffRole::Teacher,
        ]);

        $this->actingAs($this->admin)
            ->put(route('staff.credentials', $member), [
                'password' => 'Torrent-Vault-88!',
                'password_confirmation' => 'Torrent-Vault-88!',
            ])
            ->assertSessionHasNoErrors();

        expect(Hash::check('Torrent-Vault-88!', $member->fresh()->password))->toBeTrue();
    });

    test('a School Admin changing their own password', function () {
        $this->actingAs($this->admin)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Greenfield-College-1!',
                'password_confirmation' => 'Greenfield-College-1!',
            ])
            // This form validates into its own error bag, so the assertion has
            // to name it, against the default bag it would pass whether the
            // password was refused or not.
            ->assertSessionHasErrors('password', null, 'updatePassword');
    });

    test('the identity comes from the account, not from the request', function () {
        $member = Staff::factory()->create(['school_id' => $this->school->id]);

        // Submitting a different school name alongside the password must not
        // change which name is checked against, the school is read from the
        // account being edited.
        $this->actingAs($this->admin)
            ->put(route('staff.credentials', $member), [
                'school_name' => 'Somewhere Else Entirely',
                'password' => 'Greenfield2026!',
                'password_confirmation' => 'Greenfield2026!',
            ])
            ->assertSessionHasErrors('password');
    });
});
