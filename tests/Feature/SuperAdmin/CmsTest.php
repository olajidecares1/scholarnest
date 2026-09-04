<?php

use App\Enums\UserRole;
use App\Models\BlogPost;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the cms page', function () {
    Page::factory()->create(['title' => 'About Us']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cms.index'))
        ->assertStatus(200)
        ->assertSee('About Us');
});

test('super admin can create, update, and delete a page', function () {
    $this->actingAs($this->superAdmin)->post(route('super-admin.cms.pages.store'), [
        'title' => 'Privacy Policy',
        'body' => 'Our privacy policy content.',
        'is_published' => '1',
    ])->assertRedirect();

    $page = Page::where('title', 'Privacy Policy')->firstOrFail();
    expect($page->slug)->toBe('privacy-policy');

    $this->actingAs($this->superAdmin)->put(route('super-admin.cms.pages.update', $page), [
        'title' => 'Privacy Policy Updated',
        'body' => 'Updated content.',
        'is_published' => '0',
    ])->assertRedirect();

    expect($page->fresh()->title)->toBe('Privacy Policy Updated');
    expect($page->fresh()->is_published)->toBeFalse();

    $this->actingAs($this->superAdmin)->delete(route('super-admin.cms.pages.destroy', $page))->assertRedirect();
    expect(Page::find($page->id))->toBeNull();
});

test('super admin can create a blog post as its author', function () {
    $this->actingAs($this->superAdmin)->post(route('super-admin.cms.blog-posts.store'), [
        'title' => 'Welcome to ScholarNest',
        'excerpt' => 'An introduction.',
        'body' => 'Full content here.',
        'is_published' => '1',
    ])->assertRedirect();

    $post = BlogPost::where('title', 'Welcome to ScholarNest')->firstOrFail();
    expect($post->author_id)->toBe($this->superAdmin->id);
    expect($post->published_at)->not->toBeNull();
});

test('super admin can manage testimonials', function () {
    $this->actingAs($this->superAdmin)->post(route('super-admin.cms.testimonials.store'), [
        'name' => 'Jane Doe',
        'role' => 'Principal',
        'quote' => 'ScholarNest transformed our school.',
        'sort_order' => 1,
        'is_active' => '1',
    ])->assertRedirect();

    $testimonial = Testimonial::where('name', 'Jane Doe')->firstOrFail();

    $this->actingAs($this->superAdmin)->delete(route('super-admin.cms.testimonials.destroy', $testimonial))->assertRedirect();
    expect(Testimonial::find($testimonial->id))->toBeNull();
});

test('super admin can manage faq items', function () {
    $this->actingAs($this->superAdmin)->post(route('super-admin.cms.faq-items.store'), [
        'question' => 'How do I subscribe?',
        'answer' => 'Choose a plan from your dashboard.',
        'sort_order' => 1,
        'is_active' => '1',
    ])->assertRedirect();

    expect(FaqItem::where('question', 'How do I subscribe?')->exists())->toBeTrue();
});

test('super admin can manage team members', function () {
    $this->actingAs($this->superAdmin)->post(route('super-admin.cms.team-members.store'), [
        'name' => 'John Smith',
        'role' => 'CEO',
        'bio' => 'Founder of ScholarNest.',
        'sort_order' => 1,
        'is_active' => '1',
    ])->assertRedirect();

    expect(TeamMember::where('name', 'John Smith')->exists())->toBeTrue();
});
