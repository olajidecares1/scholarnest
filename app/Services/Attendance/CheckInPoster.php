<?php

namespace App\Services\Attendance;

use App\Models\School;
use App\Services\CodeImageGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Str;

/**
 * The piece of paper that goes on the wall by the gate.
 *
 * A3 rather than A4 because it is read from a queue of people at arm's length
 * and further, and because a QR code printed small is a QR code that has to be
 * scanned three times in the rain.
 */
class CheckInPoster
{
    public function __construct(private readonly CodeImageGenerator $codes) {}

    /**
     * Issue, or re-issue, the school's poster token.
     *
     * Re-issuing is the answer to a poster that has been photographed and
     * passed around: the old paper stops working the moment this is called, so
     * the only cost of a leak is a reprint.
     */
    public function issueToken(School $school): string
    {
        $token = Str::random(48);

        $school->forceFill(['check_in_token' => $token])->save();

        return $token;
    }

    /**
     * Where the poster sends a phone.
     *
     * The central host, not the school's own domain, on every plan. A school
     * can move its website to a custom domain, or lose it, without the paper
     * on the wall quietly becoming a dead link.
     */
    public function url(School $school): string
    {
        return route('check-in.show', ['school' => $school, 'token' => $school->check_in_token]);
    }

    public function document(School $school): PdfDocument
    {
        $url = $this->url($school);

        return Pdf::loadView('check-in.poster', [
            'school' => $school,
            'url' => $url,

            // Generated locally, as a data URI: dompdf cannot fetch remote
            // images, see App\Services\CodeImageGenerator.
            'qrCode' => $this->codes->qrCodeDataUri($url, size: 900, margin: 12),
            // Contained, not cropped: a school crest with its motto round the
            // edge loses the motto to a crop.
            'logo' => $school->logoAbsolutePath()
                ? $this->codes->containedImageDataUri($school->logoAbsolutePath(), 260, 260)
                : null,
        ])->setPaper('a3', 'portrait');
    }

    public function filename(School $school): string
    {
        return Str::slug($school->name ?: 'school').'-check-in-poster.pdf';
    }
}
