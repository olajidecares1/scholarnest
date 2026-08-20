<?php

use App\Enums\UserRole;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can publish a news post', function () {
    $response = $this->actingAs($this->admin)->post(route('news.store'), [
        'title' => 'Marvel Students Win Science Fair',
        'body' => 'Our students took first place at the regional science fair.',
        'category' => 'Achievements',
    ]);

    $response->assertRedirect();

    $post = NewsPost::where('title', 'Marvel Students Win Science Fair')->firstOrFail();
    expect($post->school_id)->toBe($this->school->id);
    expect($post->is_published)->toBeTrue();
});

test('a school admin only sees news posts from their own school', function () {
    NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Post']);
    $otherSchool = School::factory()->create();
    NewsPost::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Post']);

    $this->actingAs($this->admin)
        ->get(route('news.index'))
        ->assertSee('Own Post')
        ->assertDontSee('Other Post');
});

test('a school admin can update a news post', function () {
    $post = NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put(route('news.update', $post), [
            'title' => 'New Title',
            'body' => $post->body,
        ])
        ->assertRedirect();

    expect($post->fresh()->title)->toBe('New Title');
});

test('a school admin cannot update another school\'s news post', function () {
    $otherSchool = School::factory()->create();
    $post = NewsPost::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('news.update', $post), ['title' => 'Hacked', 'body' => 'Hacked body'])
        ->assertForbidden();
});

test('a school admin can delete a news post', function () {
    $post = NewsPost::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('news.destroy', $post))
        ->assertRedirect();

    expect(NewsPost::find($post->id))->toBeNull();
});
