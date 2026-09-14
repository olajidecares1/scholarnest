<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/**
 * A school called "Grace & Mercy Academy" must be stored, and shown, as
 * "Grace & Mercy Academy".
 *
 * THE BUG, and it was a data one rather than a display one. Callers wrote
 * `value="{{ $school->name }}"` on <x-text-field>, so Blade escaped the value
 * into a plain string and the component's own `value="{{ $resolvedValue }}"`
 * escaped it a second time. The browser decoded one layer when the form was
 * submitted, so "Grace & Mercy" came back as "Grace &amp; Mercy" and was SAVED
 * that way, and every subsequent save added another layer. A live school was
 * found stored as "Arise &amp;amp; Shine Int School".
 *
 * The fix is at the call sites, not in the rendering: a bound `:value` passes
 * the raw value and the component escapes it exactly once. Escaping itself is
 * untouched, so the XSS protection is exactly where it was.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Grace & Mercy Academy']), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    // SHARED, not passed as render data. A Blade component gets a fresh scope,
    // so $errors has to arrive the way it normally does, from a view share,
    // which in a real request is ShareErrorsFromSession's job.
    View::share('errors', new ViewErrorBag);
});

describe('the component escapes exactly once', function () {
    test('a bound value survives a round trip through the field', function () {
        $rendered = Blade::render(
            '<x-text-field name="name" label="Name" :value="$value" />',
            ['value' => 'Grace & Mercy Academy'],
        );

        // Escaped once for the attribute, which is correct and is what keeps
        // it safe, and not twice.
        expect($rendered)->toContain('value="Grace &amp; Mercy Academy"')
            ->not->toContain('&amp;amp;');
    });

    test('quotes and angle brackets are escaped, not doubled', function () {
        $rendered = Blade::render(
            '<x-text-field name="motto" label="Motto" :value="$value" />',
            ['value' => '"Excellence & Integrity" <always>'],
        );

        expect($rendered)->not->toContain('&amp;amp;')
            ->not->toContain('&amp;quot;')
            ->not->toContain('&amp;lt;');
    });

    test('an apostrophe is left readable', function () {
        $rendered = Blade::render(
            '<x-text-field name="name" label="Name" :value="$value" />',
            ['value' => "St. Mary's Academy"],
        );

        expect($rendered)->not->toContain('&amp;#039;')
            ->not->toContain('&amp;amp;');
    });
});

describe('no view passes an interpolated string into a component value', function () {
    test('every x-text-field uses a bound value', function () {
        // The regression guard. One `value="{{ ... }}"` on a component is all
        // it takes to start corrupting a column again, and the corruption is
        // invisible until somebody types an ampersand.
        $offenders = [];

        // Walked rather than globbed: glob's ** is not recursive everywhere.
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (preg_match('/<x-text-field\b[^>]*\svalue="\{\{/s', $contents) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        expect($offenders)->toBe([]);
    });
});

describe('the school name renders correctly everywhere', function () {
    test('on the settings form it is escaped once', function () {
        $html = $this->actingAs($this->admin)->get(route('settings.index'))->assertOk()->getContent();

        expect($html)->toContain('value="Grace &amp; Mercy Academy"')
            ->not->toContain('&amp;amp;');
    });

    test('and saving it back does not add a layer', function () {
        // The actual corruption path: open the form, save it unchanged, and
        // watch the name grow an encoding each time.
        $before = $this->school->fresh()->getRawOriginal('name');

        $this->actingAs($this->admin)->put(route('settings.update'), [
            'name' => $before,
            'current_session' => $this->school->current_session ?: '2025/2026',
        ]);

        expect($this->school->fresh()->getRawOriginal('name'))->toBe('Grace & Mercy Academy');
    });
});

describe('the repair command', function () {
    test('it decodes until stable, so a triple encoding comes all the way back', function () {
        $school = School::factory()->create(['name' => 'Arise &amp;amp; Shine Int School']);

        $this->artisan('akademicnest:normalise-encoded-text')->assertSuccessful();

        expect($school->fresh()->getRawOriginal('name'))->toBe('Arise & Shine Int School');
    });

    test('it leaves ordinary text completely alone', function () {
        $untouched = School::factory()->create(['name' => "St. Mary's & Sons (Nigeria) Ltd."]);

        $this->artisan('akademicnest:normalise-encoded-text')->assertSuccessful();

        expect($untouched->fresh()->getRawOriginal('name'))->toBe("St. Mary's & Sons (Nigeria) Ltd.");
    });

    test('--dry-run writes nothing', function () {
        $school = School::factory()->create(['name' => 'Arise &amp;amp; Shine Int School']);

        $this->artisan('akademicnest:normalise-encoded-text --dry-run')->assertSuccessful();

        expect($school->fresh()->getRawOriginal('name'))->toBe('Arise &amp;amp; Shine Int School');
    });
});

describe('escaping is still doing its job', function () {
    test('a script tag in a school name is never rendered as markup', function () {
        // The one thing this fix must not have cost. Storing the real text and
        // escaping on output is exactly how it should work, the danger would
        // be storing it escaped and then rendering it raw.
        $school = activateSchool(School::factory()->create([
            'name' => '<script>alert(1)</script>',
        ]), PlanKey::Standard);

        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $html = $this->actingAs($admin)->get(route('settings.index'))->assertOk()->getContent();

        expect($html)->not->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;');
    });
});
