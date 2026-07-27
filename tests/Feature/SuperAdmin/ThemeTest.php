<?php

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the themes page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.themes.index'))
        ->assertStatus(200)
        ->assertSee('Color Theme');
});

test('super admin can switch the color theme and it applies platform-wide', function () {
    $this->actingAs($this->superAdmin)->put(route('super-admin.themes.update'), [
        'theme_preset' => 'emerald-green',
    ])->assertRedirect();

    expect(Setting::current()->theme_preset)->toBe('emerald-green');

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
    $response->assertSee('--color-primary-500: #10b981;', false);
});

test('an invalid theme preset is rejected', function () {
    $this->actingAs($this->superAdmin)->put(route('super-admin.themes.update'), [
        'theme_preset' => 'not-a-real-preset',
    ])->assertSessionHasErrors('theme_preset');
});

test('super admin can upload a new logo', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.themes.logo.update'), [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect();

    $settings = Setting::current();
    expect($settings->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->logo_path);
});

test('super admin can upload a new favicon', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.themes.favicon.update'), [
        'favicon' => UploadedFile::fake()->image('favicon.png'),
    ]);

    $response->assertRedirect();

    $settings = Setting::current();
    expect($settings->favicon_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->favicon_path);
});
