<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\PlatformWhatsAppSetting;
use App\Models\School;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null, 'services.whatsapp.result_template' => null, 'services.whatsapp.verify_template' => null]);
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    // Meta: a GET checks the number, a POST sends.
    $this->metaRejects = false;
    Http::fake(['graph.facebook.com/*' => fn ($request) => $this->metaRejects
        ? Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401)
        : ($request->method() === 'GET'
        ? Http::response(['display_phone_number' => '+234 900 000 0001', 'verified_name' => 'AkademicNest', 'id' => '777000111'])
        : Http::response(['messages' => [['id' => 'wamid.PLATFORM']]]))]);
});

function savePlatformWhatsApp(object $test): void
{
    $test->actingAs($test->superAdmin)->put(route('super-admin.settings.whatsapp.update'), [
        'phone_number_id' => '777000111',
        'access_token' => 'EAAG-platform-token-0123456789',
        'result_template' => 'result_details',
        'verify_template' => 'verify_code',
        'template_language' => 'en',
        'app_secret' => 'app-secret-123',
    ])->assertRedirect(route('super-admin.settings.whatsapp'));
}

test('the Super Admin switches automatic WhatsApp on for every school, checked with Meta, secrets encrypted', function () {
    $this->actingAs($this->superAdmin)->get(route('super-admin.settings.whatsapp'))->assertOk()->assertSee('Automatic WhatsApp is off');
    expect(app(WhatsAppGateway::class)->isConfigured())->toBeFalse();

    savePlatformWhatsApp($this);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/777000111') && $r->hasHeader('Authorization', 'Bearer EAAG-platform-token-0123456789'));

    expect(app(WhatsAppGateway::class)->isConfigured())->toBeTrue()
        ->and(app(WhatsAppGateway::class)->canSendCodes())->toBeTrue()
        ->and(DB::table('platform_whatsapp_settings')->value('access_token'))->not->toContain('EAAG')
        ->and(DB::table('platform_whatsapp_settings')->value('app_secret'))->not->toContain('app-secret');

    // Every school, with no setup of its own.
    $school = activateSchool(School::factory()->create(), PlanKey::Basic);
    expect(app(WhatsAppGateway::class)->forSchool($school)->isConfigured())->toBeTrue();

    $this->actingAs($this->superAdmin)->get(route('super-admin.settings.whatsapp'))
        ->assertSee('Automatic WhatsApp is on: +234 900 000 0001 (AkademicNest)')
        ->assertSee(PlatformWhatsAppSetting::current()->webhook_verify_token)
        ->assertDontSee('EAAG-platform-token-0123456789')
        ->assertDontSee('app-secret-123');
});

test('messages go from the number saved in Super Admin', function () {
    savePlatformWhatsApp($this);

    $gateway = app(WhatsAppGateway::class);
    $gateway->send('2348031234567', 'x', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], $gateway->resultTemplate());

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_contains($r->url(), '/777000111/messages')
        && $r->hasHeader('Authorization', 'Bearer EAAG-platform-token-0123456789')
        && $r['template']['name'] === 'result_details');
});

test('details Meta does not accept are not saved', function () {
    $this->metaRejects = true;

    $this->actingAs($this->superAdmin)->put(route('super-admin.settings.whatsapp.update'), [
        'phone_number_id' => '777000111', 'access_token' => 'EAAG-wrong-0123456789-xyz', 'result_template' => 'result_details', 'template_language' => 'en',
    ])->assertSessionHasErrors('access_token');

    expect(app(WhatsAppGateway::class)->isConfigured())->toBeFalse();
});

test('a test message can be sent, and the details removed', function () {
    savePlatformWhatsApp($this);

    $this->actingAs($this->superAdmin)->post(route('super-admin.settings.whatsapp.test'), ['test_phone' => '0803 123 4567'])
        ->assertSessionHas('status', 'Test message sent to +234 803 123 4567. Check that phone.');

    $this->actingAs($this->superAdmin)->delete(route('super-admin.settings.whatsapp.destroy'));
    expect(app(WhatsAppGateway::class)->isConfigured())->toBeFalse();
});

test('only the Super Admin can reach it', function () {
    $school = activateSchool(School::factory()->create(), PlanKey::Basic);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('super-admin.settings.whatsapp'))->assertForbidden();
});
