<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'Bright Future Academy']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can view the settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertStatus(200)
        ->assertSee('School Profile')
        ->assertSee('Bright Future Academy');
});

test('a school admin can update the school name, timezone, and session', function () {
    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => 'New Name Academy',
        'timezone' => 'Africa/Lagos',
        'current_session' => '2026/2027',
    ])->assertRedirect();

    $this->school->refresh();
    expect($this->school->name)->toBe('New Name Academy');
    expect($this->school->timezone)->toBe('Africa/Lagos');
    expect($this->school->current_session)->toBe('2026/2027');
});

test('a school admin can upload a school logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => $this->school->name,
        'timezone' => 'Africa/Lagos',
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ]);

    $this->school->refresh();
    expect($this->school->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->school->logo_path);
});

test('a school admin can upload a school favicon', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => $this->school->name,
        'timezone' => 'Africa/Lagos',
        'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
    ]);

    $this->school->refresh();
    expect($this->school->favicon_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->school->favicon_path);
});

test('an invalid timezone is rejected', function () {
    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => $this->school->name,
        'timezone' => 'Not/AZone',
    ])->assertSessionHasErrors('timezone');
});
