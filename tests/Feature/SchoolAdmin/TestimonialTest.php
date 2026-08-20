<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a testimonial', function () {
    $response = $this->actingAs($this->admin)->post(route('testimonials.store'), [
        'name' => 'Mrs. Adebayo',
        'role' => 'Parent',
        'quote' => 'A wonderful school with dedicated teachers.',
    ]);

    $response->assertRedirect();

    $testimonial = Testimonial::where('name', 'Mrs. Adebayo')->firstOrFail();
    expect($testimonial->school_id)->toBe($this->school->id);
    expect($testimonial->is_active)->toBeTrue();
});

test('a school admin only sees testimonials from their own school, not global ones', function () {
    Testimonial::factory()->create(['school_id' => $this->school->id, 'name' => 'Own Testimonial']);
    Testimonial::factory()->create(['school_id' => null, 'name' => 'Platform Testimonial']);
    $otherSchool = School::factory()->create();
    Testimonial::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Other School Testimonial']);

    $this->actingAs($this->admin)
        ->get(route('testimonials.index'))
        ->assertSee('Own Testimonial')
        ->assertDontSee('Platform Testimonial')
        ->assertDontSee('Other School Testimonial');
});

test('a school admin can update a testimonial', function () {
    $testimonial = Testimonial::factory()->create(['school_id' => $this->school->id, 'name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('testimonials.update', $testimonial), [
            'name' => 'New Name',
            'quote' => $testimonial->quote,
        ])
        ->assertRedirect();

    expect($testimonial->fresh()->name)->toBe('New Name');
});

test('a school admin cannot modify a global platform testimonial', function () {
    $testimonial = Testimonial::factory()->create(['school_id' => null]);

    $this->actingAs($this->admin)
        ->put(route('testimonials.update', $testimonial), ['name' => 'Hacked', 'quote' => 'Hacked'])
        ->assertForbidden();
});

test('a school admin cannot modify another school\'s testimonial', function () {
    $otherSchool = School::factory()->create();
    $testimonial = Testimonial::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('testimonials.destroy', $testimonial))
        ->assertForbidden();
});

test('a school admin can delete a testimonial', function () {
    $testimonial = Testimonial::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('testimonials.destroy', $testimonial))
        ->assertRedirect();

    expect(Testimonial::find($testimonial->id))->toBeNull();
});
