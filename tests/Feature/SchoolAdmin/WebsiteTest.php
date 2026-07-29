<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'Bright Future Academy']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school gets a unique slug automatically on creation', function () {
    expect($this->school->slug)->not->toBeNull();

    $other = School::factory()->create(['name' => 'Bright Future Academy']);
    expect($other->slug)->not->toBe($this->school->slug);
});

test('a school admin can view the website settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('website.index'))
        ->assertStatus(200)
        ->assertSee('Website Content');
});

test('a school admin can update the website content', function () {
    $this->actingAs($this->admin)->put(route('website.update'), [
        'hero_title' => 'Welcome to Bright Future',
        'about_text' => 'We are a leading school.',
    ])->assertRedirect();

    expect($this->school->fresh()->website->hero_title)->toBe('Welcome to Bright Future');
});

test('a school admin can upload a hero image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->put(route('website.update'), [
        'hero_title' => 'Welcome',
        'hero_image' => UploadedFile::fake()->image('hero.jpg', 800, 400),
    ]);

    $website = $this->school->fresh()->website;
    expect($website->hero_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($website->hero_image_path);
});

test('a school admin can publish and unpublish the website', function () {
    $this->actingAs($this->admin)->post(route('website.publish'));
    expect($this->school->fresh()->website->is_published)->toBeTrue();

    $this->actingAs($this->admin)->post(route('website.publish'));
    expect($this->school->fresh()->website->is_published)->toBeFalse();
});

test('the public website page 404s when unpublished', function () {
    $this->get(route('public.school-website', $this->school))->assertNotFound();
});

test('the public website page shows content once published', function () {
    $this->actingAs($this->admin)->put(route('website.update'), [
        'hero_title' => 'Welcome to Bright Future',
        'about_text' => 'We are a leading school.',
    ]);
    $this->actingAs($this->admin)->post(route('website.publish'));

    $this->get(route('public.school-website', $this->school))
        ->assertStatus(200)
        ->assertSee('Welcome to Bright Future')
        ->assertSee('We are a leading school.');
});

test('a school admin can add and remove a gallery image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('website.gallery.store'), [
        'image' => UploadedFile::fake()->image('photo.jpg', 600, 400),
        'caption' => 'Sports Day',
    ]);

    $image = SchoolGalleryImage::where('school_id', $this->school->id)->firstOrFail();
    expect($image->caption)->toBe('Sports Day');
    Storage::disk('public')->assertExists($image->image_path);

    $this->actingAs($this->admin)->delete(route('website.gallery.destroy', $image));
    expect(SchoolGalleryImage::find($image->id))->toBeNull();
});

test('a school admin cannot remove another school\'s gallery image', function () {
    $otherSchool = School::factory()->create();
    $image = SchoolGalleryImage::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('website.gallery.destroy', $image))
        ->assertForbidden();
});
