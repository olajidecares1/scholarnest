<?php

use App\Enums\UserRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\School;
use App\Models\SupportTicket;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('resource urls expose a uuid instead of the internal sequential id', function () {
    $school = School::factory()->create();

    expect($school->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    expect(route('super-admin.schools.show', $school))
        ->toEndWith('/'.$school->uuid)
        ->not->toEndWith('/'.$school->id);
});

test('a school can be reached by its uuid but not by its raw sequential id', function () {
    $school = School::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertStatus(200);

    $this->actingAs($this->superAdmin)
        ->get("/super-admin/schools/{$school->id}")
        ->assertStatus(404);
});

test('a media item can be reached by its uuid but not by its raw sequential id', function () {
    $media = Media::factory()->create();

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.media.update', $media), ['name' => 'Renamed'])
        ->assertRedirect();

    $this->actingAs($this->superAdmin)
        ->put("/super-admin/media/{$media->id}", ['name' => 'Renamed Again'])
        ->assertStatus(404);
});

test('a support ticket can be reached by its uuid but not by its raw sequential id', function () {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.support-tickets.show', $ticket))
        ->assertStatus(200);

    $this->actingAs($this->superAdmin)
        ->get("/super-admin/support-tickets/{$ticket->id}")
        ->assertStatus(404);
});

test('a cms page can be reached by its uuid but not by its raw sequential id', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.cms.pages.update', $page), [
            'title' => 'Updated Title',
            'body' => 'Updated body.',
        ])
        ->assertRedirect();

    $this->actingAs($this->superAdmin)
        ->put("/super-admin/cms/pages/{$page->id}", [
            'title' => 'Should Not Work',
            'body' => 'Should not work.',
        ])
        ->assertStatus(404);
});
