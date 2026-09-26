<?php

use App\Enums\UserRole;
use App\Models\LegalDocument;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Release 1.2: one button, one hover, <small> for descriptions, in every
 * portal, and the version number where people can see it.
 *
 * The first three are guarded by reading the views rather than by rendering a
 * handful of pages, because the point of the change is that it holds on EVERY
 * page, including the ones added after it.
 */

/**
 * The views of the School Admin, Parent, Student and Staff portals (and the
 * sign-in pages and Team screens around them). Printed documents, emails and
 * the public school website keep their own design.
 *
 * @return list<string>
 */
function portalViews(): array
{
    $dirs = ['auth', 'components', 'guardian', 'portal', 'profile', 'school-admin', 'school-portal', 'staff', 'student', 'subscriptions', 'super-admin', 'support-tickets'];
    $files = [];

    foreach ($dirs as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path("views/{$dir}")));

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (str_ends_with($path, '.blade.php')
                && ! preg_match('#/pdf/|print\.blade|_report-card|/report-card\.blade|template-preview|components/(public-site-layout|website-blocks|school-map|legal-layout|job-portal-layout)\.blade#', $path)) {
                $files[] = $path;
            }
        }
    }

    return $files;
}

test('the app is version 1.2 and says so in the portals', function () {
    expect(config('app.version'))->toBe('1.2');

    $school = School::factory()->create();
    activateSchool($school);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('students.index'))->assertOk()->assertSee('Version 1.2');

    foreach (['guardian', 'staff', 'student'] as $portal) {
        expect(file_get_contents(resource_path("views/components/{$portal}-layout.blade.php")))->toContain('<x-app-version');
    }
});

test('every legal document is at version 1.2', function () {
    foreach (LegalDocument::all() as $document) {
        expect($document->version)->toBe('1.2')
            ->and($document->body)->not->toContain('**Version 1.1');
    }
});

test('a legal document still at 1.1 is moved to 1.2, and an edited one is left alone', function () {
    $document = LegalDocument::where('slug', 'privacy')->firstOrFail();
    $document->update(['version' => '1.1', 'body' => "# Privacy\n\n**Version 1.1 · Effective soon · Last updated 13 September 2026**\n\nText."]);
    LegalDocument::where('slug', 'terms')->update(['version' => '2.0']);

    $migration = require database_path('migrations/2026_09_26_120000_bump_legal_documents_to_version_1_2.php');
    $migration->up();

    expect($document->fresh()->version)->toBe('1.2')
        ->and($document->fresh()->body)->toContain('**Version 1.2 · Effective soon · Last updated 26 September 2026**')
        ->and(DB::table('legal_documents')->where('slug', 'terms')->value('version'))->toBe('2.0');
});

test('buttons and fields share the registration dimensions and a 1.5px hover border', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--field-height: 36px;')
        ->toContain('--btn-height: var(--field-height);')
        ->toContain('--control-hover-border-width: 1.5px;')
        ->toMatch('/\.btn:hover:not\([^)]*\)[^{]*\{[^}]*border-width: var\(--control-hover-border-width\);/s')
        ->toMatch('/select:hover:not\([^)]*\)[^{]*\{[^}]*border-width: var\(--control-hover-border-width\);/s');
});

test('every filled or outlined text button in the portals carries .btn', function () {
    $missing = [];

    foreach (portalViews() as $path) {
        preg_match_all('/<(button|a)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s', file_get_contents($path), $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            if (! preg_match('/(?<![:\w@.-])class="([^"]*)"/', $tag[2], $class)) {
                continue;
            }

            $tokens = preg_split('/\s+/', preg_replace('/\{\{.*?\}\}/s', ' ', $class[1]));
            $has = fn (string $pattern) => (bool) preg_grep($pattern, $tokens);

            $looksLikeButton = $has('/^rounded-\[(6|8)px\]$/')
                && $has('/^px-/') && $has('/^py-/')
                && $has('/^text-(xs|sm)$/')
                && ($has('/^bg-(blue|primary|green|red|amber|gray)-(5|6|7|9)00$/') || in_array('border', $tokens, true))
                && ! $has('/^(flex-col|justify-between|border-b-2)$/');

            if ($looksLikeButton && ! in_array('btn', $tokens, true)) {
                $missing[] = str_replace(resource_path('views/'), '', $path).': '.substr($class[1], 0, 80);
            }
        }
    }

    expect($missing)->toBe([]);
});

test('description text in the portals is written on <small>, not <p>', function () {
    $paragraphs = [];

    foreach (portalViews() as $path) {
        preg_match_all('/<p\b[^>]*\bclass="([^"]*)"/', file_get_contents($path), $matches);

        foreach ($matches[1] as $class) {
            $tokens = preg_split('/\s+/', $class);
            $hint = in_array('field-hint', $tokens, true);
            $muted = (bool) preg_grep('/^text-gray-(400|500)$/', $tokens);
            $sized = (bool) preg_grep('/^text-(xs|sm)$/', $tokens);
            $emphatic = (bool) preg_grep('/^(font-(bold|extrabold|black)|text-(lg|xl|2xl|3xl))$/', $tokens);

            if ($hint || ($muted && $sized && ! $emphatic)) {
                $paragraphs[] = str_replace(resource_path('views/'), '', $path).': '.$class;
            }
        }
    }

    expect($paragraphs)->toBe([]);
});

test('field icons are Font Awesome, and every path a form passes has a real equivalent', function () {
    foreach (['text-field', 'select-field', 'textarea-field'] as $component) {
        expect(file_get_contents(resource_path("views/components/{$component}.blade.php")))->not->toContain('<svg');
    }

    $unknown = [];

    foreach (portalViews() as $path) {
        preg_match_all('/<x-[\w-]*field\b[^>]*?(?<![:\w-])icon="([^"]*)"/s', file_get_contents($path), $matches);

        foreach ($matches[1] as $icon) {
            if (! \App\Support\FieldIcon::knows($icon)) {
                $unknown[] = str_replace(resource_path('views/'), '', $path).': '.substr($icon, 0, 40);
            }
        }
    }

    expect($unknown)->toBe([])
        ->and(\App\Support\FieldIcon::fa('fa-user'))->toBe('fa-user')
        ->and(\App\Support\FieldIcon::fa('M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7'))->toBe('fa-envelope')
        ->and(\App\Support\FieldIcon::fa('M1 1'))->toBe(\App\Support\FieldIcon::FALLBACK)
        ->and(\App\Support\FieldIcon::fa(null))->toBeNull();
});

test('icons inside buttons are Font Awesome, not hand-drawn SVG', function () {
    $svgs = [];

    foreach (portalViews() as $path) {
        preg_match_all('/<button\b.*?<\/button>|<(a|label)\b[^>]*class="btn [^"]*"[^>]*>.*?<\/\1>/s', file_get_contents($path), $matches);

        foreach ($matches[0] as $button) {
            if (str_contains($button, '<svg')) {
                $svgs[] = str_replace(resource_path('views/'), '', $path);
            }
        }
    }

    expect(array_values(array_unique($svgs)))->toBe([]);
});

test('the sign-in and find-your-school pages carry the 1.2 buttons and small print', function () {
    foreach (['portal/login', 'portal/basic/finder', 'school-portal/admin-login', 'school-portal/index', 'guardian/auth/login', 'staff/auth/login', 'student/auth/login', 'auth/register'] as $view) {
        $html = file_get_contents(resource_path("views/{$view}.blade.php"));

        expect($html)->toContain('<small')->not->toContain('<svg class="h-4 w-4"');

        if ($view !== 'school-portal/index') {
            expect($html)->toContain('class="btn ');
        }
    }
});
