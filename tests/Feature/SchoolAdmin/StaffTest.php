<?php

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can view their staff list', function () {
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($this->admin)
        ->get(route('staff.index'))
        ->assertStatus(200)
        ->assertSee('Amaka Obi');
});

test('a school admin only sees staff from their own school', function () {
    $otherSchool = School::factory()->create();
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    Staff::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Tunde', 'last_name' => 'Bello']);

    $this->actingAs($this->admin)
        ->get(route('staff.index'))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('a school admin can add a staff member', function () {
    $response = $this->actingAs($this->admin)->post(route('staff.store'), [
        'staff_number' => 'STF-0001',
        'first_name' => 'Chinedu',
        'last_name' => 'Eze',
        'gender' => Gender::Male->value,
        'role' => StaffRole::Teacher->value,
        'department' => 'Mathematics',
        'emergency_contact_name' => 'Ngozi Eze',
        'emergency_contact_phone' => '08012345678',
    ]);

    $response->assertRedirect();

    $member = Staff::where('staff_number', 'STF-0001')->firstOrFail();
    expect($member->school_id)->toBe($this->school->id);
    expect($member->fullName())->toBe('Chinedu Eze');
    expect($member->is_active)->toBeTrue();
});

test('staff numbers must be unique within a school', function () {
    Staff::factory()->create(['school_id' => $this->school->id, 'staff_number' => 'STF-0001']);

    $this->actingAs($this->admin)
        ->post(route('staff.store'), [
            'staff_number' => 'STF-0001',
            'first_name' => 'Second',
            'last_name' => 'Staff',
            'gender' => Gender::Female->value,
            'role' => StaffRole::Teacher->value,
        ])
        ->assertSessionHasErrors('staff_number');
});

test('two different schools can reuse the same staff number', function () {
    $otherSchool = School::factory()->create();
    Staff::factory()->create(['school_id' => $otherSchool->id, 'staff_number' => 'STF-0001']);

    $this->actingAs($this->admin)
        ->post(route('staff.store'), [
            'staff_number' => 'STF-0001',
            'first_name' => 'Chinedu',
            'last_name' => 'Eze',
            'gender' => Gender::Male->value,
            'role' => StaffRole::Teacher->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('a school admin can upload a staff photo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('staff.store'), [
        'staff_number' => 'STF-0002',
        'first_name' => 'Bisi',
        'last_name' => 'Ade',
        'gender' => Gender::Female->value,
        'role' => StaffRole::Administrator->value,
        'photo' => UploadedFile::fake()->image('bisi.jpg', 400, 400),
    ]);

    $member = Staff::where('staff_number', 'STF-0002')->firstOrFail();
    expect($member->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($member->photo_path);
});

test('a school admin can view a staff profile', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($this->admin)
        ->get(route('staff.show', $member))
        ->assertStatus(200)
        ->assertSee('Amaka Obi');
});

test('a school admin cannot view another school\'s staff member', function () {
    $otherSchool = School::factory()->create();
    $member = Staff::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->get(route('staff.show', $member))
        ->assertForbidden();
});

test('a school admin can update a staff member', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'department' => 'Mathematics']);

    $this->actingAs($this->admin)
        ->put(route('staff.update', $member), [
            'staff_number' => $member->staff_number,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'gender' => $member->gender->value,
            'role' => $member->role->value,
            'department' => 'English Language',
        ])
        ->assertRedirect();

    expect($member->fresh()->department)->toBe('English Language');
});

test('a school admin cannot update another school\'s staff member', function () {
    $otherSchool = School::factory()->create();
    $member = Staff::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('staff.update', $member), [
            'staff_number' => $member->staff_number,
            'first_name' => 'Hacked',
            'last_name' => 'Name',
            'gender' => $member->gender->value,
            'role' => $member->role->value,
        ])
        ->assertForbidden();
});

test('a school admin can toggle a staff member\'s active status', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('staff.toggle-active', $member))
        ->assertRedirect();

    expect($member->fresh()->is_active)->toBeFalse();
});

test('a school admin can delete a staff member', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('staff.destroy', $member))
        ->assertRedirect();

    expect(Staff::find($member->id))->toBeNull();
});

test('a school admin cannot delete another school\'s staff member', function () {
    $otherSchool = School::factory()->create();
    $member = Staff::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('staff.destroy', $member))
        ->assertForbidden();

    expect(Staff::find($member->id))->not->toBeNull();
});

test('staff can be searched by name or staff number', function () {
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'staff_number' => 'STF-0001']);
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'staff_number' => 'STF-0002']);

    $this->actingAs($this->admin)
        ->get(route('staff.index', ['search' => 'Amaka']))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('staff can be filtered by role', function () {
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'role' => StaffRole::Teacher]);
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'role' => StaffRole::Administrator]);

    $this->actingAs($this->admin)
        ->get(route('staff.index', ['role' => StaffRole::Teacher->value]))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('a school admin can set a staff member\'s portal password', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'password' => null]);

    $this->actingAs($this->admin)
        ->put(route('staff.update-password', $member), [
            'password' => 'NewPortal@123',
        ])
        ->assertRedirect();

    $this->assertTrue(Hash::check('NewPortal@123', $member->fresh()->password));
});

test('a school admin cannot set a portal password for another school\'s staff member', function () {
    $otherSchool = School::factory()->create();
    $member = Staff::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('staff.update-password', $member), [
            'password' => 'NewPortal@123',
        ])
        ->assertForbidden();
});

test('the dashboard shows the real total staff count', function () {
    Staff::factory()->count(3)->create(['school_id' => $this->school->id, 'is_active' => true]);
    Staff::factory()->create(['school_id' => $this->school->id, 'is_active' => false]);

    $response = $this->actingAs($this->admin)->get(route('dashboard'));

    $response->assertSeeInOrder(['Teachers & Staff', '4']);
});
