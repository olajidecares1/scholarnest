<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use App\Support\Storage\DatabaseStorageFallback;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves PUBLIC-disk files kept in the database - school logos, website,
 * gallery and news images, CBT question images, media library files.
 *
 * Only the "public" disk, and only while it is the database. Private files -
 * photographs of people, signatures, receipts, documents - are never reachable
 * here; they go through the controllers that check who is asking.
 *
 * Every public upload is stored under a new random name, so an address always
 * means the same bytes and is cached for a year. Byte ranges are honoured,
 * because Safari will not play a video without them.
 */
class StoredFileController extends Controller
{
    private const MAX_AGE = 31536000;

    public function show(Request $request, string $path): Response
    {
        abort_unless(config('filesystems.disks.public.driver') === DatabaseStorageFallback::DRIVER, 404);

        $file = StoredFile::locate('public', $path);

        abort_if($file === null, 404);

        // Only what the public disk is for. Anything else - however it got
        // there - is never rendered from this origin.
        abort_unless(preg_match('#^(image/(jpeg|png|webp|gif|x-icon|vnd\.microsoft\.icon)|video/(mp4|quicktime|webm))$#', (string) $file->mime_type) === 1, 404);

        $headers = [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'public, max-age='.self::MAX_AGE.', immutable',
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Last-Modified' => $file->updated_at?->toRfc7231String(),
        ];

        [$start, $end] = $this->range($request, $file->size);

        if ($start === null) {
            return new Response('', 416, ['Content-Range' => "bytes */{$file->size}"]);
        }

        $partial = $request->headers->has('Range') && ($start > 0 || $end < $file->size - 1);

        $headers['Content-Length'] = (string) ($end - $start + 1);

        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$file->size}";
        }

        return new StreamedResponse(function () use ($file, $start, $end) {
            $output = fopen('php://output', 'wb');
            $file->copyRangeTo($output, $start, $end);
            fclose($output);
        }, $partial ? 206 : 200, $headers);
    }

    /**
     * The single byte range asked for, or the whole file.
     *
     * @return array{0: int|null, 1: int}
     */
    private function range(Request $request, int $size): array
    {
        $whole = [0, max(0, $size - 1)];
        $header = (string) $request->headers->get('Range', '');

        if ($header === '' || ! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $match)) {
            return $whole;
        }

        [, $from, $to] = $match;

        if ($from === '' && $to === '') {
            return $whole;
        }

        if ($from === '') {
            // The last N bytes.
            $start = max(0, $size - (int) $to);

            return [$start, $size - 1];
        }

        $start = (int) $from;
        $end = $to === '' ? $size - 1 : min((int) $to, $size - 1);

        return $start >= $size || $start > $end ? [null, 0] : [$start, $end];
    }
}
