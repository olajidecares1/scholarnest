<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
    $this->school->website()->create(['hero_title' => 'Welcome']);
    $this->actingAs($this->admin)->post(route('website.publish'));
});

test('a school with no favicon renders an empty icon link, never ScholarNest\'s own favicon', function () {
    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('<link rel="icon" href="data:,">', false);
});

test('a school with an uploaded favicon renders it in the icon link', function () {
    $this->school->update(['favicon_path' => 'school-favicons/test-favicon.png']);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee($this->school->faviconUrl(), false);
});
