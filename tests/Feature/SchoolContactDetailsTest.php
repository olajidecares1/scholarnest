<?php

use App\Enums\IdCardHolderType;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\IssuedIdCard;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\Student;
use App\Models\User;
use App\Support\IdCardSample;
use App\Support\SchoolContact;
use App\Support\SchoolMotto;
use App\Support\SchoolSocialLinks;

/**
 * A school's address used to live on its website record, and the website is a
 * Standard and Exclusive feature - so the two places an address matters most,
 * the letterhead on a result sheet and the back of an ID card, were the two a
 * Basic school could not fill in.
 */
beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'Bright Star Academy']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a Basic school can set its own address', function () {
    activateSchool($this->school, PlanKey::Basic);

    $this->actingAs($this->admin)
        ->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'contact_address' => '5 Unity Road, Ikeja, Lagos.',
            'contact_phone' => '+234 701 222 3333',
            'contact_email' => 'hello@brightstar.example',
        ])
        ->assertRedirect();

    $school = $this->school->fresh();

    expect($school->contact_address)->toBe('5 Unity Road, Ikeja, Lagos.')
        ->and($school->contact_phone)->toBe('+234 701 222 3333')
        ->and($school->contact_email)->toBe('hello@brightstar.example');
});

test('the school settings page offers the fields on every plan', function () {
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Address &amp; Contact', false)
            ->assertSee('name="contact_address"', false)
            ->assertSee('name="contact_phone"', false)
            ->assertSee('name="contact_email"', false);

        auth()->logout();
    }
});

test('the school\'s own details win over its website', function () {
    $this->school->update([
        'contact_address' => 'Settings address',
        'contact_phone' => 'Settings phone',
    ]);

    SchoolWebsite::factory()->create([
        'school_id' => $this->school->id,
        'contact_address' => 'Website address',
        'contact_phone' => 'Website phone',
        'contact_email' => 'website@example.test',
    ]);

    $contact = SchoolContact::for($this->school->fresh());

    expect($contact->address)->toBe('Settings address')
        ->and($contact->phone)->toBe('Settings phone')

        // Not overridden, so the website still supplies it - a Standard school
        // that filled in its website and never opened Settings keeps working.
        ->and($contact->email)->toBe('website@example.test');
});

test('a school with nothing anywhere reports itself empty', function () {
    // Nothing in settings, no website, nothing derivable. The card uses this
    // to leave the whole block out rather than draw a heading over blank space.
    expect(SchoolContact::for($this->school)->isEmpty())->toBeTrue();

    $this->school->update(['contact_phone' => '+234 700 000 0000']);

    expect(SchoolContact::for($this->school->fresh())->isEmpty())->toBeFalse();
});

test('the ID card back prints the address a Basic school set in settings', function () {
    $basic = activateSchool(School::factory()->create(['name' => 'Basic Plan School']), PlanKey::Basic);

    $basic->update([
        'contact_address' => '5 Unity Road, Ikeja, Lagos.',
        'contact_phone' => '+234 701 222 3333',
    ]);

    // No website record at all - a Basic school does not get one.
    expect($basic->fresh()->website)->toBeNull();

    $card = IdCardSample::for($basic->fresh(), IdCardHolderType::Student);

    foreach (['_card_back', 'pdf._back'] as $partial) {
        $html = view("school-admin.id-cards.{$partial}", ['card' => $card])->render();

        expect($html)->toContain('If found, please return to:')
            ->and($html)->toContain('5 Unity Road, Ikeja, Lagos.')
            ->and($html)->toContain('+234 701 222 3333');
    }
});

test('the back leaves the return block out when there is nothing to print', function () {
    // A heading with nothing beneath it is worse than no heading.
    $bare = School::factory()->create([
        'name' => 'No Details School',
        'contact_address' => null,
        'contact_phone' => null,
        'contact_email' => null,
        'slug' => null,
    ]);

    $card = IdCardSample::for($bare->fresh(), IdCardHolderType::Student);

    expect(SchoolContact::for($bare->fresh())->isEmpty())->toBeTrue();

    foreach (['_card_back', 'pdf._back'] as $partial) {
        expect(view("school-admin.id-cards.{$partial}", ['card' => $card])->render())
            ->not->toContain('If found, please return to:');
    }
});

test('a real card, not just the sample, reads the school settings', function () {
    activateSchool($this->school, PlanKey::Standard);

    $this->school->update(['contact_address' => '9 Marina Street, Lagos Island.']);

    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $card = IssuedIdCard::factory()->create([
        'school_id' => $this->school->id,
        'holder_type' => IdCardHolderType::Student,
        'holder_uuid' => $student->uuid,
        'issued_by' => $this->admin->id,
    ]);

    expect(view('school-admin.id-cards._card_back', ['card' => $card->fresh()])->render())
        ->toContain('9 Marina Street, Lagos Island.');
});

test('a Basic school\'s result letterhead carries its address', function () {
    // The other half of the same gap: results printed with no address for any
    // school that could not have a website.
    activateSchool($this->school, PlanKey::Basic);

    $this->school->update([
        'contact_address' => '5 Unity Road, Ikeja, Lagos.',
        'contact_phone' => '+234 701 222 3333',
        'contact_email' => 'hello@brightstar.example',
    ]);

    foreach ([
        'views/school-admin/results/_report-card.blade.php',
        'views/school-admin/results/pdf/report-card.blade.php',
    ] as $view) {
        $markup = file_get_contents(resource_path($view));

        // Reads the school's own details, not the plan-gated website record.
        expect($markup)->toContain('SchoolContact::for($school)')
            ->and($markup)->not->toContain('website?->contact_address')
            ->and($markup)->not->toContain('website->contact_address');
    }
});

// ---------------------------------------------------------------------------
// Social handles
// ---------------------------------------------------------------------------

test('a Basic school can set every social handle', function () {
    activateSchool($this->school, PlanKey::Basic);

    $this->actingAs($this->admin)
        ->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'facebook_url' => 'facebook.com/brightstar',
            'instagram_url' => 'https://instagram.com/brightstar',
            'tiktok_url' => 'tiktok.com/@brightstar',
            'twitter_url' => 'x.com/brightstar',
            'youtube_url' => 'youtube.com/@brightstar',
            'linkedin_url' => 'linkedin.com/company/brightstar',
            'whatsapp_number' => '08031234567',
        ])
        ->assertRedirect();

    expect($this->school->fresh()->tiktok_url)->toBe('tiktok.com/@brightstar')
        ->and($this->school->fresh()->whatsapp_number)->toBe('08031234567');
});

test('the settings page offers every network the brief asked for', function () {
    activateSchool($this->school, PlanKey::Basic);

    $response = $this->actingAs($this->admin)->get(route('settings.index'))->assertOk();

    foreach (['facebook_url', 'instagram_url', 'tiktok_url', 'twitter_url', 'youtube_url', 'linkedin_url', 'whatsapp_number'] as $field) {
        $response->assertSee('name="'.$field.'"', false);
    }

    $response->assertSee('Social Handles');
});

test('a handle typed without a scheme still becomes a working link', function () {
    // Schools write "facebook.com/ourschool" as often as they paste a full
    // address, and an href without a scheme is read as a path on our own site.
    $this->school->update(['facebook_url' => 'facebook.com/brightstar']);

    $links = SchoolSocialLinks::for($this->school->fresh());

    expect($links[0]['url'])->toBe('https://facebook.com/brightstar');
});

test('a full URL is left exactly as the school typed it', function () {
    $this->school->update(['instagram_url' => 'https://instagram.com/brightstar']);

    expect(SchoolSocialLinks::for($this->school->fresh())[0]['url'])
        ->toBe('https://instagram.com/brightstar');
});

test('a WhatsApp number becomes a wa.me link, dialled properly', function () {
    // wa.me wants digits with the country code and no leading zero.
    expect(SchoolSocialLinks::whatsappUrl('08031234567'))->toBe('https://wa.me/2348031234567')
        ->and(SchoolSocialLinks::whatsappUrl('+234 803 123 4567'))->toBe('https://wa.me/2348031234567')

        // Too short to dial, so no link rather than a broken one.
        ->and(SchoolSocialLinks::whatsappUrl('12345'))->toBeNull()
        ->and(SchoolSocialLinks::whatsappUrl(null))->toBeNull();
});

test('only the networks a school actually set come back', function () {
    $this->school->update(['facebook_url' => 'facebook.com/a', 'whatsapp_number' => '08031234567']);

    $links = SchoolSocialLinks::for($this->school->fresh());

    expect(collect($links)->pluck('label')->all())->toBe(['Facebook', 'WhatsApp']);
});

test('the school\'s own handle wins over the one on its website', function () {
    $this->school->update(['facebook_url' => 'facebook.com/from-settings']);

    SchoolWebsite::factory()->create([
        'school_id' => $this->school->id,
        'facebook_url' => 'https://facebook.com/from-website',
        'instagram_url' => 'https://instagram.com/from-website',
    ]);

    $links = collect(SchoolSocialLinks::for($this->school->fresh()))->keyBy('label');

    expect($links['Facebook']['url'])->toBe('https://facebook.com/from-settings')

        // Untouched in settings, so the website still supplies it.
        ->and($links['Instagram']['url'])->toBe('https://instagram.com/from-website');
});

test('the public site renders every network, not just the original three', function () {
    // The footer and contact page used to hand-write Facebook, X and Instagram
    // against the website record, so a school could set TikTok in Settings and
    // see it nowhere.
    foreach ([
        'views/components/public-site-layout.blade.php',
        'views/public/school-contact.blade.php',
    ] as $view) {
        $markup = file_get_contents(resource_path($view));

        expect($markup)->toContain('SchoolSocialLinks::for($school)')
            ->and($markup)->not->toContain('$website->facebook_url')
            ->and($markup)->not->toContain('$website->instagram_url');
    }
});

test('a Basic school can set its motto and core values', function () {
    // Same story as the address: both lines lived on the website record, so a
    // Basic school's report cards printed its name twice and carried no values.
    activateSchool($this->school, PlanKey::Basic);

    $this->actingAs($this->admin)
        ->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'motto' => 'Raising Excellence, Building Leaders',
            'core_values' => 'Discipline - Knowledge - Character - Excellence',
        ])
        ->assertRedirect();

    $school = $this->school->fresh();

    expect($school->motto)->toBe('Raising Excellence, Building Leaders')
        ->and($school->core_values)->toBe('Discipline - Knowledge - Character - Excellence');
});

test('the settings page offers the motto fields on every plan', function () {
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Motto &amp; Core Values', false)
            ->assertSee('name="motto"', false)
            ->assertSee('name="core_values"', false);

        auth()->logout();
    }
});

test('the motto resolves the school first, then its website', function () {
    $school = School::factory()->create(['motto' => 'What Settings says']);
    SchoolWebsite::factory()->create([
        'school_id' => $school->id,
        'slogan_tagline' => 'What the website says',
        'footer_text' => 'Discipline - Knowledge - Character - Excellence',
    ]);
    $school->load('website');

    $motto = SchoolMotto::for($school);

    // The school's own value wins; the website fills the gap it left.
    expect($motto->tagline)->toBe('What Settings says')
        ->and($motto->values)->toBe('Discipline - Knowledge - Character - Excellence');
});

test('the two lines never collapse into the same sentence', function () {
    // Reading both from one column printed the slogan under the school's name
    // AND along the foot of the same page.
    $school = School::factory()->create();
    SchoolWebsite::factory()->create(['school_id' => $school->id, 'slogan' => 'One Slogan Only']);
    $school->load('website');

    $motto = SchoolMotto::for($school);

    expect($motto->tagline)->toBe('One Slogan Only')
        ->and($motto->values)->toBeNull();
});
