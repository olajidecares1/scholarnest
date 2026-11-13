<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Rules\SchoolAddressAvailable;
use App\Support\SchoolAddress;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
 * One registered school per physical address.
 *
 * Same name, different address: allowed.
 * Different name, same address: refused.
 * Same address, different email: refused.
 * Same address, same email: refused.
 */

function registerSchoolAt(string $address, string $email, string $name = 'Greenfield Academy')
{
    return test()->post(route('register'), [
        'school_name' => $name,
        'email' => $email,
        'phone' => '08012345678',
        'address' => $address,
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
        'terms' => '1',
    ]);
}

test('addresses written differently are recognised as the same place', function (string $a, string $b) {
    expect(SchoolAddress::fingerprint($a))->toBe(SchoolAddress::fingerprint($b));
})->with([
    'abbreviations and punctuation' => ['No. 12, Allen Ave., Ikeja, Lagos State', '12 allen avenue ikeja lagos'],
    'capitals and spacing' => ['12   ALLEN  AVENUE,IKEJA', '12 Allen Avenue Ikeja'],
    'road and close' => ['5 Adeola Odeku Rd, VI', '5 Adeola Odeku Road VI'],
    'house letters' => ['12B Bode Thomas St', '12 b Bode Thomas Street'],
    'hash numbers and country' => ['#3 Aminu Kano Cres, Wuse 2, Abuja, Nigeria', '3 Aminu Kano Crescent Wuse 2 Abuja'],
    'apostrophes and opposite' => ["St. John's Rd, Opp. Total filling station", 'Street Johns Road opposite total filling station'],
]);

test('different buildings are different places', function (string $a, string $b) {
    expect(SchoolAddress::fingerprint($a))->not->toBe(SchoolAddress::fingerprint($b));
})->with([
    'house number' => ['12 Allen Avenue, Ikeja', '14 Allen Avenue, Ikeja'],
    'house letter' => ['12A Allen Avenue, Ikeja', '12B Allen Avenue, Ikeja'],
    'town' => ['3 Market Road, Aba', '3 Market Road, Onitsha'],
]);

test('a school can register with its address', function () {
    registerSchoolAt('12 Allen Avenue, Ikeja, Lagos', 'admin@greenfield.example')
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $school = School::sole();
    expect($school->contact_address)->toBe('12 Allen Avenue, Ikeja, Lagos')
        ->and($school->billing_address)->toBe('12 Allen Avenue, Ikeja, Lagos')
        ->and($school->address_fingerprint)->toBe(SchoolAddress::fingerprint('12 allen avenue ikeja lagos'));
});

test('two schools with the same name at different addresses can both register', function () {
    registerSchoolAt('12 Allen Avenue, Ikeja, Lagos', 'one@greenfield.example', 'Greenfield Academy')->assertSessionHasNoErrors();
    auth()->logout();
    registerSchoolAt('4 Ring Road, Ibadan, Oyo', 'two@greenfield.example', 'Greenfield Academy')->assertSessionHasNoErrors();

    expect(School::where('name', 'Greenfield Academy')->count())->toBe(2);
});

test('a second registration at the same address is refused, whatever the name or email', function (string $name, string $email, string $address) {
    registerSchoolAt('No. 12, Allen Ave., Ikeja, Lagos State', 'admin@greenfield.example', 'Greenfield Academy')->assertSessionHasNoErrors();
    auth()->logout();

    $response = registerSchoolAt($address, $email, $name);

    $response->assertSessionHasErrors(['address' => SchoolAddressAvailable::MESSAGE]);
    $this->assertGuest();
    expect(School::count())->toBe(1)
        ->and(User::where('role', UserRole::SchoolAdmin)->count())->toBe(1);
})->with([
    'different name, same address' => ['Bright Stars College', 'head@brightstars.example', '12 Allen Avenue, Ikeja, Lagos'],
    'same name, different email' => ['Greenfield Academy', 'other@greenfield.example', '12 allen avenue ikeja lagos'],
    'same name, same email' => ['Greenfield Academy', 'admin@greenfield.example', '12 ALLEN AVE IKEJA LAGOS'],
]);

test('the refusal never reveals anything about the school already registered', function () {
    registerSchoolAt('12 Allen Avenue, Ikeja, Lagos', 'secret-head@greenfield.example', 'Greenfield Academy');
    auth()->logout();

    $this->from(route('register'));
    registerSchoolAt('12 Allen Avenue, Ikeja, Lagos', 'someone@else.example', 'Another School');

    $page = $this->get(route('register'))->assertOk();

    $page->assertSee('This school is already registered')
        ->assertSee('duplicate registrations are not permitted')
        ->assertDontSee('secret-head@greenfield.example')
        ->assertDontSee('Greenfield Academy');
});

test('an address must be given, and specific enough to identify a building', function (?string $address) {
    registerSchoolAt((string) $address, 'admin@greenfield.example')->assertSessionHasErrors('address');

    expect(School::count())->toBe(0);
})->with([
    'missing' => [''],
    'a town only' => ['Lagos'],
    'too vague' => ['Ikeja Lagos'],
]);

test('the database refuses a second school at the same address even if the check is bypassed', function () {
    School::factory()->create(['contact_address' => '12 Allen Avenue, Ikeja, Lagos']);

    // Two registrations arriving together both pass the validation check; the
    // unique index is what stops the second.
    expect(fn () => School::factory()->create(['contact_address' => '12 allen ave. ikeja, lagos']))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(School::count())->toBe(1);
});

test('a school with no address yet blocks nobody', function () {
    School::factory()->count(2)->create(['contact_address' => null]);

    expect(School::whereNull('address_fingerprint')->count())->toBe(2);
});

test('a school admin cannot move their address onto another school\'s, or blank it', function () {
    School::factory()->create(['contact_address' => '4 Ring Road, Ibadan, Oyo']);
    $school = School::factory()->create(['contact_address' => '12 Allen Avenue, Ikeja, Lagos']);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school);

    $form = fn (?string $address) => [
        'name' => $school->name,
        'timezone' => 'Africa/Lagos',
        'contact_address' => $address,
    ];

    $this->actingAs($admin)->put(route('settings.update'), $form('4 Ring Rd, Ibadan, Oyo State'))
        ->assertSessionHasErrors(['contact_address' => SchoolAddressAvailable::MESSAGE]);

    $this->actingAs($admin)->put(route('settings.update'), $form(''))
        ->assertSessionHasErrors('contact_address');

    // Its own address, reformatted, is fine.
    $this->actingAs($admin)->put(route('settings.update'), $form('12 Allen Ave, Ikeja, Lagos'))
        ->assertSessionDoesntHaveErrors('contact_address');

    expect($school->fresh()->contact_address)->toBe('12 Allen Ave, Ikeja, Lagos');
});

test('the AkademicNest Team cannot add a second school at the same address', function () {
    School::factory()->create(['contact_address' => '12 Allen Avenue, Ikeja, Lagos']);
    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($team)->post(route('super-admin.schools.store'), [
        'school_name' => 'Bright Stars College',
        'school_address' => '12 Allen Ave., Ikeja, Lagos State',
        'admin_name' => 'Jane Doe',
        'admin_email' => 'jane@brightstars.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors(['school_address' => SchoolAddressAvailable::MESSAGE]);

    expect(School::count())->toBe(1)->and(User::where('email', 'jane@brightstars.test')->exists())->toBeFalse();
});

test('schools that already shared an address are reported, never changed', function () {
    // As they were before this rule existed: two schools, one address.
    $first = School::factory()->create(['contact_address' => '12 Allen Avenue, Ikeja, Lagos']);
    $second = School::factory()->create(['contact_address' => null]);
    DB::table('schools')->where('id', $second->id)->update(['contact_address' => '12 allen ave, ikeja, lagos', 'address_fingerprint' => null]);

    $this->artisan('schools:duplicate-addresses')
        ->expectsOutputToContain('1 address(es) are shared by more than one school. Nothing has been changed.')
        ->assertSuccessful();

    expect(School::count())->toBe(2)
        ->and($first->fresh()->address_fingerprint)->not->toBeNull()
        ->and($second->fresh()->address_fingerprint)->toBeNull()
        ->and($second->fresh()->contact_address)->toBe('12 allen ave, ikeja, lagos');
});

test('the registration form asks for the school address', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('School Address')
        ->assertSee('name="address"', false)
        ->assertSee('autocomplete="street-address"', false);
});
