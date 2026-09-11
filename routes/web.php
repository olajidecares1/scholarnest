<?php

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| This file used to be 94 KB of route definitions in one list, which made
| answering "what does this application expose?" a scrolling exercise. It is
| now a manifest: each area is its own file, and this is the order they are
| registered in.
|
| THE ORDER IS PART OF THE BEHAVIOUR, not a tidy-up. Laravel's router takes
| the FIRST route matching a method and URI, so:
|
|   - public.php goes first because its custom-domain group is constrained by
|     Host. A host-agnostic route for the same URI registered earlier would
|     win even on a foreign domain, and every school's own domain would then
|     serve the wrong page.
|
|   - school-links.php goes last because both of its routes sit in the root
|     namespace - /{school:result_link_slug}/result and /{school:slug} - and
|     would otherwise shadow everything registered after them.
|
| Each file carries the reasoning for its own contents. Adding an area means
| adding a file and a line here, in the right place.
|
*/

require __DIR__.'/public.php';

// Before school-links.php claims the root namespace - /sw.js lives there too.
require __DIR__.'/pwa.php';

require __DIR__.'/student.php';
require __DIR__.'/guardian.php';
require __DIR__.'/staff.php';
require __DIR__.'/portal-entry.php';

require __DIR__.'/authenticated.php';

require __DIR__.'/school-links.php';
