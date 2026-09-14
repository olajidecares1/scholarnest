<?php

use Illuminate\Support\Facades\Route;

/**
 * Every route('...') name written anywhere actually exists.
 *
 * THIS TEST EXISTS BECAUSE OF A REAL 500. A parallel password-reset flow was
 * deleted, and one Blade file still called route('admin.password-reset.request')
 * by name. The registration page threw for every visitor, and the cleanup had
 * been "verified" by grepping for the deleted CLASS, which found nothing,
 * because the surviving reference was a plain string in a template.
 *
 * A route name is the one kind of reference no editor and no static analyser
 * follows: it is a string on one side and a fluent call in a routes file on the
 * other. Deleting a route therefore fails at RUN TIME, on whichever page nobody
 * happened to open, which for an obfuscated admin path can be a long time.
 *
 * Only literal names are checked. route($variable) and route("prefix.{$x}")
 * cannot be resolved without running the code, so they are skipped rather than
 * guessed at.
 */
test('no view or class references a route name that does not exist', function () {
    $registered = collect(Route::getRoutes()->getRoutesByName())->keys()->all();

    $missing = [];

    foreach (['app', 'resources/views', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $found = preg_match_all(
                // The global route() helper only. The lookbehind rules out
                // $request->route('school') and $this->route(...), which read a
                // route PARAMETER rather than name a route, a different method
                // that happens to share the word.
                '/(?<![>:$\w])route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/',
                file_get_contents($file->getPathname()),
                $matches
            );

            if (! $found) {
                continue;
            }

            foreach (array_unique($matches[1]) as $name) {
                if (in_array($name, $registered, true)) {
                    continue;
                }

                $relative = str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()));

                $missing[] = "{$name}  ({$relative})";
            }
        }
    }

    expect(array_unique($missing))->toBe([]);
});

test('the deleted reset flow is gone from the route table', function () {
    // The callers are covered by the test above, which is the half that
    // actually failed: the routes were removed and the application still threw,
    // because one Blade file outlived them.
    //
    // Deliberately not a text search for "admin.password-reset", the comments
    // explaining why it was removed contain the name, and a test that forbids
    // writing down what happened would only get the explanation deleted.
    foreach (['admin.password-reset.request', 'admin.password-reset.show', 'admin.password-reset.complete'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});

test('the pages that reset flow was reachable from all render', function () {
    // The registration page is where the 500 actually surfaced: it embeds the
    // hidden Super Admin dialog, which held the dead link.
    $this->get(route('register'))->assertOk();
    $this->get(route('password.request'))->assertOk();
    $this->get(route('legal.index'))->assertOk();
});
