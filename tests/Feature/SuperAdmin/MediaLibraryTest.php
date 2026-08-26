<?php

use App\Enums\MediaType;
use App\Enums\UserRole;
use App\Models\AdminRole;
use App\Models\Media;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the media library', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.media.index'))
        ->assertStatus(200)
        ->assertSee('Media');
});

test('super admin can upload an image', function () {
    $file = UploadedFile::fake()->image('campus.jpg', 1200, 800);

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.media.store'), [
        'file' => $file,
        'name' => 'Campus Photo',
    ]);

    $response->assertRedirect();

    $media = Media::where('name', 'Campus Photo')->firstOrFail();
    expect($media->type)->toBe(MediaType::Image);
    Storage::disk('public')->assertExists($media->path);
});

test('super admin can upload a video', function () {
    $file = UploadedFile::fake()->create('promo.mp4', 2048, 'video/mp4');

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.media.store'), [
        'file' => $file,
        'name' => 'Promo Video',
    ]);

    $response->assertRedirect();

    $media = Media::where('name', 'Promo Video')->firstOrFail();
    expect($media->type)->toBe(MediaType::Video);
    Storage::disk('public')->assertExists($media->path);
});

test('super admin can rename a media item', function () {
    $media = Media::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.media.update', $media), ['name' => 'New Name'])
        ->assertRedirect();

    expect($media->fresh()->name)->toBe('New Name');
});

test('super admin can replace a media file while keeping the same reference', function () {
    $original = UploadedFile::fake()->image('old.jpg');
    $originalPath = $original->store('media', 'public');

    $media = Media::factory()->create([
        'path' => $originalPath,
        'type' => MediaType::Image,
    ]);

    $replacement = UploadedFile::fake()->image('new.jpg', 900, 600);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.media.replace', $media), ['file' => $replacement])
        ->assertRedirect();

    $media->refresh();
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($media->path);
    expect($media->original_filename)->toBe('new.jpg');
});

test('super admin can delete a media item', function () {
    $file = UploadedFile::fake()->image('to-delete.jpg');
    $path = $file->store('media', 'public');
    $media = Media::factory()->create(['path' => $path]);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.media.destroy', $media))
        ->assertRedirect();

    expect(Media::find($media->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('deleting a media item in use as a background clears the setting', function () {
    $media = Media::factory()->create();
    Setting::current()->update([
        'login_background_media_id' => $media->id,
        'register_background_media_id' => $media->id,
    ]);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.media.destroy', $media))
        ->assertRedirect();

    $settings = Setting::current()->fresh();
    expect($settings->login_background_media_id)->toBeNull();
    expect($settings->register_background_media_id)->toBeNull();
});

test('super admin can set login and register backgrounds', function () {
    $media = Media::factory()->create();

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.media.backgrounds.update'), [
            'login_background_media_id' => $media->id,
            'register_background_media_id' => $media->id,
        ])
        ->assertRedirect();

    $settings = Setting::current()->fresh();
    expect($settings->login_background_media_id)->toBe($media->id);
    expect($settings->register_background_media_id)->toBe($media->id);
});

test('the school admin login page renders the configured background image', function () {
    $media = Media::factory()->create(['type' => MediaType::Image]);
    Setting::current()->update(['login_background_media_id' => $media->id]);

    // The setting followed the sign-in itself: the shared login page is gone,
    // and School Admins now sign in at their own school's portal.
    $school = School::factory()->create();

    $this->get(route('portal.admin.login', [$school, $school->portal_admin_token]))
        ->assertStatus(200)
        ->assertSee($media->url(), false);
});

test('the register page renders the configured background video', function () {
    $media = Media::factory()->create(['type' => MediaType::Video]);
    Setting::current()->update(['register_background_media_id' => $media->id]);

    $this->get(route('register'))
        ->assertStatus(200)
        ->assertSee('<video', false)
        ->assertSee($media->url(), false);
});

test('a team member without manage_media permission is forbidden from the media library', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.media.index'))
        ->assertForbidden();
});

test('a team member with manage_media permission can access the media library', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_media']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.media.index'))
        ->assertStatus(200);
});
