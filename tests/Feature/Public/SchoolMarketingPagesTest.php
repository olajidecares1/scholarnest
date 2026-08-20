<?php

use App\Enums\UserRole;
use App\Models\JobPosting;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolFacility;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
    $this->school->website()->create([
        'hero_title' => 'Welcome to Our School',
        'about_text' => 'A great place to learn.',
    ]);
    $this->actingAs($this->admin)->post(route('website.publish'));
});

test('the public homepage shows published news, events, and jobs', function () {
    NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Big Announcement', 'is_published' => true]);
    NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Hidden Draft', 'is_published' => false]);
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Sports Day', 'starts_at' => now()->addWeek()]);
    JobPosting::factory()->create(['school_id' => $this->school->id, 'title' => 'Science Teacher', 'is_active' => true]);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('Big Announcement')
        ->assertDontSee('Hidden Draft')
        ->assertSee('Sports Day')
        ->assertSee('Science Teacher');
});

test('the public news index only lists published posts for that school', function () {
    NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Visible Post', 'is_published' => true]);
    NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Draft Post', 'is_published' => false]);

    $otherSchool = School::factory()->create();
    NewsPost::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other School Post', 'is_published' => true]);

    $this->get(route('public.school-news.index', $this->school))
        ->assertOk()
        ->assertSee('Visible Post')
        ->assertDontSee('Draft Post')
        ->assertDontSee('Other School Post');
});

test('a single news post page shows the full story', function () {
    $post = NewsPost::factory()->create(['school_id' => $this->school->id, 'title' => 'Full Story Title', 'body' => 'The complete article body.']);

    $this->get(route('public.school-news.show', [$this->school, $post]))
        ->assertOk()
        ->assertSee('Full Story Title')
        ->assertSee('The complete article body.');
});

test('an unpublished news post 404s on the public site', function () {
    $post = NewsPost::factory()->create(['school_id' => $this->school->id, 'is_published' => false]);

    $this->get(route('public.school-news.show', [$this->school, $post]))->assertNotFound();
});

test('a news post from another school 404s', function () {
    $otherSchool = School::factory()->create();
    $post = NewsPost::factory()->create(['school_id' => $otherSchool->id]);

    $this->get(route('public.school-news.show', [$this->school, $post]))->assertNotFound();
});

test('the public events index lists upcoming events', function () {
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Future Event', 'starts_at' => now()->addWeek()]);
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Past Event', 'starts_at' => now()->subWeek()]);

    $this->get(route('public.school-events.index', $this->school))
        ->assertOk()
        ->assertSee('Future Event')
        ->assertDontSee('Past Event');
});

test('the public careers index only lists open job postings', function () {
    JobPosting::factory()->create(['school_id' => $this->school->id, 'title' => 'Open Role', 'is_active' => true]);
    JobPosting::factory()->create(['school_id' => $this->school->id, 'title' => 'Closed Role', 'is_active' => false]);

    $this->get(route('public.school-careers.index', $this->school))
        ->assertOk()
        ->assertSee('Open Role')
        ->assertDontSee('Closed Role');
});

test('the public gallery index renders', function () {
    $this->get(route('public.school-gallery.index', $this->school))->assertOk();
});

test('public marketing pages 404 when the website is unpublished', function () {
    $unpublishedSchool = School::factory()->create();

    $this->get(route('public.school-news.index', $unpublishedSchool))->assertNotFound();
    $this->get(route('public.school-events.index', $unpublishedSchool))->assertNotFound();
    $this->get(route('public.school-careers.index', $unpublishedSchool))->assertNotFound();
    $this->get(route('public.school-gallery.index', $unpublishedSchool))->assertNotFound();
    $this->get(route('public.school-admissions.index', $unpublishedSchool))->assertNotFound();
    $this->get(route('public.school-facilities.index', $unpublishedSchool))->assertNotFound();
});

test('the public admissions page shows the process and requirements', function () {
    $this->school->website->update([
        'admissions_intro' => 'We welcome new families.',
        'admissions_steps' => [['title' => 'Submit Enquiry', 'description' => 'Reach out to us first.']],
        'admissions_requirements' => ['Birth certificate'],
    ]);

    $this->get(route('public.school-admissions.index', $this->school))
        ->assertOk()
        ->assertSee('We welcome new families.')
        ->assertSee('Submit Enquiry')
        ->assertSee('Birth certificate');
});

test('the public facilities index lists facilities', function () {
    SchoolFacility::factory()->create(['school_id' => $this->school->id, 'name' => 'Science Laboratory']);

    $this->get(route('public.school-facilities.index', $this->school))
        ->assertOk()
        ->assertSee('Science Laboratory');
});

test('the public homepage shows active testimonials and facilities', function () {
    Testimonial::factory()->create(['school_id' => $this->school->id, 'name' => 'Happy Parent', 'is_active' => true]);
    Testimonial::factory()->create(['school_id' => $this->school->id, 'name' => 'Hidden Testimonial', 'is_active' => false]);
    SchoolFacility::factory()->create(['school_id' => $this->school->id, 'name' => 'ICT Laboratory']);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('Happy Parent')
        ->assertDontSee('Hidden Testimonial')
        ->assertSee('ICT Laboratory');
});
