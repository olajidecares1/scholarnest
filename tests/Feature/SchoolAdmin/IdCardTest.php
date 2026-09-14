<?php

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\IdCardTemplate;
use App\Models\IssuedIdCard;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function idCardSchoolAdmin(PlanKey $planKey = PlanKey::Standard, SubscriptionStatus $status = SubscriptionStatus::Active): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('a school admin on the basic plan cannot access ID card management', function () {
    $admin = idCardSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('id-cards.index'))
        ->assertForbidden();
});

test('a school admin with no active subscription cannot access ID card management', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // Blocked earlier by the school_activated gate (no subscription at all) before
    // the ID-card plan-tier check ever runs, redirected to the dashboard, not 403'd.
    $this->actingAs($admin)
        ->get(route('id-cards.index'))
        ->assertRedirect(route('dashboard'));
});

test('a school admin on the standard plan can access ID card management', function () {
    $admin = idCardSchoolAdmin(PlanKey::Standard);

    $this->actingAs($admin)
        ->get(route('id-cards.index'))
        ->assertStatus(200);
});

test('a school admin on the exclusive plan can access ID card management', function () {
    $admin = idCardSchoolAdmin(PlanKey::Exclusive);

    $this->actingAs($admin)
        ->get(route('id-cards.index'))
        ->assertStatus(200);
});

test('a school admin can view the ID card templates page', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create(['school_id' => $admin->school_id, 'name' => 'My Custom Template']);

    $this->actingAs($admin)
        ->get(route('id-cards.templates.index'))
        ->assertStatus(200)
        ->assertSee('My Custom Template');
});

test('a new id card template defaults to portrait at the database level when orientation is omitted', function () {
    $admin = idCardSchoolAdmin();

    $id = DB::table('id_card_templates')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'school_id' => $admin->school_id,
        'name' => 'No Orientation Specified',
        'type' => IdCardHolderType::Student->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('id_card_templates')->find($id);
    expect($row->orientation)->toBe('portrait');
});

test('a school admin can create an ID card template', function () {
    $admin = idCardSchoolAdmin();

    $this->actingAs($admin)->post(route('id-cards.templates.store'), [
        'name' => 'Standard Student Card',
        'type' => IdCardHolderType::Student->value,
        'orientation' => IdCardOrientation::Portrait->value,
        'primary_color' => '#1d4ed8',
        'secondary_color' => '#111a35',
        'show_blood_group' => '1',
        'is_default' => '1',
    ])->assertRedirect();

    $template = IdCardTemplate::where('name', 'Standard Student Card')->firstOrFail();
    expect($template->school_id)->toBe($admin->school_id);
    expect($template->show_blood_group)->toBeTrue();
    expect($template->show_dob)->toBeFalse();
    expect($template->is_default)->toBeTrue();
});

test('a school admin can update an ID card template and unchecking a box clears it', function () {
    $admin = idCardSchoolAdmin();
    $template = IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'show_blood_group' => true,
    ]);

    $this->actingAs($admin)->put(route('id-cards.templates.update', $template), [
        'name' => 'Updated Card',
        'type' => IdCardHolderType::Student->value,
        'orientation' => IdCardOrientation::Portrait->value,
        'primary_color' => '#000000',
        'secondary_color' => '#ffffff',
    ])->assertRedirect();

    $template->refresh();
    expect($template->name)->toBe('Updated Card');
    expect($template->show_blood_group)->toBeFalse();
});

test('a school admin cannot update another school\'s ID card template', function () {
    $admin = idCardSchoolAdmin();
    $otherSchool = School::factory()->create();
    $template = IdCardTemplate::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)->put(route('id-cards.templates.update', $template), [
        'name' => 'Hacked',
        'type' => IdCardHolderType::Student->value,
        'orientation' => IdCardOrientation::Portrait->value,
        'primary_color' => '#000000',
        'secondary_color' => '#ffffff',
    ])->assertForbidden();
});

test('a school admin can delete an ID card template', function () {
    $admin = idCardSchoolAdmin();
    $template = IdCardTemplate::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)
        ->delete(route('id-cards.templates.destroy', $template))
        ->assertRedirect();

    expect(IdCardTemplate::find($template->id))->toBeNull();
});

test('a school admin can preview a student ID card with the default template', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($admin)
        ->get(route('id-cards.preview', ['student', $student]))
        ->assertStatus(200)
        ->assertSee('Amaka')
        ->assertSee('Obi');
});

test('a school admin can preview a teaching staff ID card with the default template', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::TeachingStaff,
        'is_default' => true,
    ]);
    $member = Staff::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'role' => StaffRole::Teacher]);

    $this->actingAs($admin)
        ->get(route('id-cards.preview', ['teaching_staff', $member]))
        ->assertStatus(200)
        ->assertSee('Tunde')
        ->assertSee('Bello');
});

test('a school admin can preview a non-teaching staff ID card with the default template', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::NonTeachingStaff,
        'is_default' => true,
    ]);
    $member = Staff::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Ngozi', 'last_name' => 'Eze', 'role' => StaffRole::SupportStaff]);

    $this->actingAs($admin)
        ->get(route('id-cards.preview', ['non_teaching_staff', $member]))
        ->assertStatus(200)
        ->assertSee('Ngozi')
        ->assertSee('Eze');
});

test('the ID card index page separates teaching and non-teaching staff', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create(['school_id' => $admin->school_id, 'type' => IdCardHolderType::TeachingStaff, 'is_default' => true]);
    IdCardTemplate::factory()->create(['school_id' => $admin->school_id, 'type' => IdCardHolderType::NonTeachingStaff, 'is_default' => true]);
    Staff::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'role' => StaffRole::Teacher]);
    Staff::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Ngozi', 'last_name' => 'Eze', 'role' => StaffRole::SupportStaff]);

    $response = $this->actingAs($admin)->get(route('id-cards.index'));

    $response->assertStatus(200)
        ->assertSee('Tunde Bello')
        ->assertSee('Ngozi Eze')
        ->assertSee('Teaching Staff')
        ->assertSee('Non-Teaching Staff');
});

test('a school admin cannot preview another school\'s student card', function () {
    $admin = idCardSchoolAdmin();
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->get(route('id-cards.preview', ['student', $student]))
        ->assertStatus(404);
});

test('a school admin can print selected student cards', function () {
    $admin = idCardSchoolAdmin();
    $template = IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $studentA = Student::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    $studentB = Student::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Tunde', 'last_name' => 'Bello']);

    $this->actingAs($admin)
        ->post(route('id-cards.print'), [
            'type' => 'student',
            'records' => [$studentA->uuid, $studentB->uuid],
            'template' => $template->uuid,
        ])
        ->assertStatus(200)
        ->assertSee('Amaka')
        ->assertSee('Obi')
        ->assertSee('Tunde')
        ->assertSee('Bello');
});

test('printing silently skips records that do not belong to the school', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $ownStudent = Student::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    $otherSchool = School::factory()->create();
    $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Foreign', 'last_name' => 'Student']);

    $this->actingAs($admin)
        ->post(route('id-cards.print'), [
            'type' => 'student',
            'records' => [$ownStudent->uuid, $foreignStudent->uuid],
        ])
        ->assertStatus(200)
        ->assertSee('Amaka')
        ->assertSee('Obi')
        ->assertDontSee('Foreign');
});

test('the show_blood_group template flag controls whether blood group appears on the card', function () {
    $admin = idCardSchoolAdmin();
    $template = IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
        'show_blood_group' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id, 'blood_group' => 'O+']);

    $this->actingAs($admin)
        ->get(route('id-cards.preview', ['student', $student]))
        ->assertSee('O+');
});

test('the student role badge always uses a fixed red colour regardless of the template primary colour', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
        'primary_color' => '#00ff00',
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $response = $this->actingAs($admin)->getJson(route('id-cards.preview', ['student', $student]));

    // The reference card's red, which the badge shares with the tagline and
    // the rule under the header. The rule this test protects is unchanged,
    // the badge ignores the school's own colours, only the shade moved, from
    // a stand-in to the one the supplied design actually uses.
    expect($response->json('front'))->toContain('#c8102e');
    expect($response->json('front'))->not->toContain('background-color: #00ff00');
});

test('the staff role badge uses the template primary colour', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::TeachingStaff,
        'is_default' => true,
        'primary_color' => '#00ff00',
    ]);
    $member = Staff::factory()->create(['school_id' => $admin->school_id, 'role' => StaffRole::Teacher]);

    $response = $this->actingAs($admin)->getJson(route('id-cards.preview', ['teaching_staff', $member]));

    expect($response->json('front'))->toContain('#00ff00');
});

test('the card back shows the default instructions wording when the template has none configured', function () {
    $admin = idCardSchoolAdmin();
    $school = $admin->school;
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
        'instructions' => null,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $response = $this->actingAs($admin)->getJson(route('id-cards.preview', ['student', $student]));

    // Escaped, because the card is HTML and the school's name is the one part
    // of this sentence the school supplies. Comparing against the raw name
    // passed only until Faker happened to generate one with an apostrophe in
    // it, "Erdman, D'Amore and Lind" renders as D&#039;Amore, which made
    // this test fail on roughly one run in ten for a reason that had nothing
    // to do with the wording it is here to check.
    expect($response->json('back'))->toContain('This card is the property of '.e($school->name).'.');
    expect($response->json('back'))->toContain('It must be worn at all times on campus.');
});

test('the card back shows the template\'s custom instructions wording when configured', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
        'instructions' => "Custom rule one.\nCustom rule two.",
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $response = $this->actingAs($admin)->getJson(route('id-cards.preview', ['student', $student]));

    expect($response->json('back'))->toContain('Custom rule one.');
    expect($response->json('back'))->toContain('Custom rule two.');
    expect($response->json('back'))->not->toContain('This card is the property of');
});

test('a school admin can set custom back-side instructions when creating a template', function () {
    $admin = idCardSchoolAdmin();

    $this->actingAs($admin)->post(route('id-cards.templates.store'), [
        'name' => 'Standard Student Card',
        'type' => IdCardHolderType::Student->value,
        'orientation' => IdCardOrientation::Portrait->value,
        'primary_color' => '#1d4ed8',
        'secondary_color' => '#111a35',
        'instructions' => "Line one.\nLine two.",
    ])->assertRedirect();

    $template = IdCardTemplate::where('name', 'Standard Student Card')->firstOrFail();
    expect($template->instructions)->toBe("Line one.\nLine two.");
});

test('previewing a card issues a numbered, persisted card', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();

    $card = IssuedIdCard::where('school_id', $admin->school_id)->where('holder_uuid', $student->uuid)->firstOrFail();
    expect($card->serial_number)->toBe(1);
    expect($card->card_number)->toContain('STU');
});

test('issuing cards for the same holder twice does not duplicate or renumber', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();
    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();

    $cards = IssuedIdCard::where('school_id', $admin->school_id)->where('holder_uuid', $student->uuid)->get();
    expect($cards)->toHaveCount(1);
});

test('card numbering is sequential per school and per holder type', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $studentA = Student::factory()->create(['school_id' => $admin->school_id]);
    $studentB = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $studentA]))->assertOk();
    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $studentB]))->assertOk();

    $cardA = IssuedIdCard::where('holder_uuid', $studentA->uuid)->firstOrFail();
    $cardB = IssuedIdCard::where('holder_uuid', $studentB->uuid)->firstOrFail();
    expect($cardA->serial_number)->toBe(1);
    expect($cardB->serial_number)->toBe(2);
});

test('numbering is isolated per school', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $otherAdmin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $otherAdmin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $otherStudent = Student::factory()->create(['school_id' => $otherAdmin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();
    $this->actingAs($otherAdmin)->get(route('id-cards.preview', ['student', $otherStudent]))->assertOk();

    $card = IssuedIdCard::where('holder_uuid', $student->uuid)->firstOrFail();
    $otherCard = IssuedIdCard::where('holder_uuid', $otherStudent->uuid)->firstOrFail();
    expect($card->serial_number)->toBe(1);
    expect($otherCard->serial_number)->toBe(1);
});

test('a newly issued card gets an expiry date at the end of the school\'s current session', function () {
    $admin = idCardSchoolAdmin();
    $admin->school->update(['current_session' => '2025/2026']);
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();

    $card = IssuedIdCard::where('holder_uuid', $student->uuid)->firstOrFail();
    expect($card->expiry_date->toDateString())->toBe('2026-07-31');
});

test('a card has no expiry date when the school has not set a current session', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();

    $card = IssuedIdCard::where('holder_uuid', $student->uuid)->firstOrFail();
    expect($card->expiry_date)->toBeNull();
});

test('the expiry date does not change when a card is reissued', function () {
    $admin = idCardSchoolAdmin();
    $admin->school->update(['current_session' => '2025/2026']);
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();
    $originalExpiry = IssuedIdCard::where('holder_uuid', $student->uuid)->firstOrFail()->expiry_date;

    $admin->school->update(['current_session' => '2026/2027']);
    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();

    $card = IssuedIdCard::where('holder_uuid', $student->uuid)->firstOrFail();
    expect($card->expiry_date->toDateString())->toBe($originalExpiry->toDateString());
});

test('a school admin can download a single ID card as a PDF', function () {
    $admin = idCardSchoolAdmin();
    $template = IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $response = $this->actingAs($admin)->post(route('id-cards.pdf'), [
        'type' => 'student',
        'records' => [$student->uuid],
        'template' => $template->uuid,
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the preview endpoint returns JSON with rendered card markup when requested via fetch', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $response = $this->actingAs($admin)->getJson(route('id-cards.preview', ['student', $student]));

    $response->assertOk()->assertJsonStructure([
        'card_number', 'has_template', 'front', 'back', 'holder_type', 'holder_uuid', 'template_uuid',
    ]);
    expect($response->json('has_template'))->toBeTrue();
    expect($response->json('front'))->toContain('Amaka');
    expect($response->json('front'))->toContain('Obi');
    expect($response->json('holder_uuid'))->toBe($student->uuid);

    $card = IssuedIdCard::where('school_id', $admin->school_id)->where('holder_uuid', $student->uuid)->firstOrFail();
    expect($response->json('card_number'))->toBe($card->card_number);
});

test('the JSON preview branch does not double-issue a card already issued by the HTML branch', function () {
    $admin = idCardSchoolAdmin();
    IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)->get(route('id-cards.preview', ['student', $student]))->assertOk();
    $this->actingAs($admin)->getJson(route('id-cards.preview', ['student', $student]))->assertOk();

    expect(IssuedIdCard::where('school_id', $admin->school_id)->where('holder_uuid', $student->uuid)->count())->toBe(1);
});

test('the preview JSON branch reports no template when none exists', function () {
    $admin = idCardSchoolAdmin();
    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    $response = $this->actingAs($admin)->getJson(route('id-cards.preview', ['student', $student]));

    $response->assertOk();
    expect($response->json('has_template'))->toBeFalse();
    expect($response->json('front'))->toBeNull();
});

test('a school admin can download a bulk sheet of ID cards as a PDF', function () {
    $admin = idCardSchoolAdmin();
    $template = IdCardTemplate::factory()->create([
        'school_id' => $admin->school_id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
    ]);
    $studentA = Student::factory()->create(['school_id' => $admin->school_id]);
    $studentB = Student::factory()->create(['school_id' => $admin->school_id]);

    $response = $this->actingAs($admin)->post(route('id-cards.pdf'), [
        'type' => 'student',
        'records' => [$studentA->uuid, $studentB->uuid],
        'template' => $template->uuid,
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
