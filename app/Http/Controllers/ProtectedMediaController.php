<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photographs of people, served from private storage.
 *
 * THEY USED TO BE PUBLIC FILES. A pupil's photograph sat at
 * /storage/students/<uuid>.jpg with no access control at all: the name was
 * unguessable, and that was the entire protection. Anyone who ever obtained the
 * address - from a shared screenshot, a referrer header, browser history, a
 * cached page - could fetch that child's photograph for ever, from any network,
 * signed in or not.
 *
 * WHY SIGNED URLS RATHER THAN A SESSION CHECK. A session check was the obvious
 * design and it does not fit: two of the pages that legitimately show a
 * photograph have no session at all. A parent opening a result with a token has
 * never signed in, and neither has somebody scanning the QR code on a printed ID
 * card to check it is genuine. Requiring a session would have broken both, and
 * the usual workaround - leave those two public - leaves the photographs exactly
 * as exposed as before.
 *
 * So the URL itself carries the authority. Whoever renders a page that may show
 * the photograph mints a short-lived signed link for it; the signature is
 * verified here before a byte is read. That gives three things the old scheme
 * had none of: the link EXPIRES, it cannot be forged without the application
 * key, and it can be scoped to one photograph rather than to the directory.
 *
 * It is deliberately not a claim that the photograph is secret from someone
 * looking over the shoulder of a person entitled to see it. It is a claim that a
 * leaked address stops working, which is the property that was missing.
 */
class ProtectedMediaController extends Controller
{
    /**
     * The models a photograph may belong to, keyed by the segment that appears
     * in the URL.
     *
     * A fixed list, so the segment can never name a class: "student" is a key
     * here, not a class name being resolved from a request.
     *
     * @var array<string, class-string>
     */
    private const SUBJECTS = [
        'student' => Student::class,
        'staff' => Staff::class,
        'guardian' => Guardian::class,
        'user' => User::class,
    ];

    /**
     * The `signed` middleware on the route has already rejected anything with a
     * missing, altered or expired signature, so by the time this runs the only
     * question left is whether the file is there.
     */
    public function photo(Request $request, string $subject, string $uuid): StreamedResponse
    {
        abort_unless(array_key_exists($subject, self::SUBJECTS), 404);

        $model = self::SUBJECTS[$subject]::where('uuid', $uuid)->first();

        abort_if($model === null || ! $model->photo_path, 404);

        $disk = $model->photoDisk();

        abort_unless($disk->exists($model->photo_path), 404);

        return $disk->response(
            $model->photo_path,
            null,
            [
                // Private, so a shared proxy or CDN never holds a copy that
                // outlives the signature on the URL that fetched it.
                'Cache-Control' => 'private, max-age=1800',
                'Content-Disposition' => 'inline',

                // These are files somebody uploaded, served inline from the
                // application's own origin. Without this a browser is free to
                // ignore the declared type and sniff the bytes - so a file
                // that passed the image check but reads as HTML would run as
                // a page here, with this origin's cookies.
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
