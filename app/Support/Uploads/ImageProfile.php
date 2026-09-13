<?php

namespace App\Support\Uploads;

/**
 * What an uploaded image is FOR, and so how it is prepared before storage.
 *
 * One list, so the decision "how big may a school logo be kept" is made once
 * rather than re-made - differently - at every upload site. Each case sets:
 *
 *  - the longest edge the stored image is scaled down to (never up), chosen
 *    from the largest size it is ever shown or printed at, with headroom for
 *    high-density screens so nothing looks soft;
 *  - how many megabytes may be uploaded, generous enough for a photograph
 *    straight off a modern phone, because the image is scaled down on arrival
 *    and what is stored is far smaller than what was sent.
 */
enum ImageProfile: string
{
    /** A school or platform logo. Transparency matters here most of all. */
    case Logo = 'logo';

    /** A browser-tab icon. Small by nature. */
    case Favicon = 'favicon';

    /** A person: student, staff, guardian or account photograph. */
    case Portrait = 'portrait';

    /** A handwritten signature photographed or scanned. */
    case Signature = 'signature';

    /** Website hero, about, cards, gallery, news, facilities, ID backgrounds. */
    case Website = 'website';

    /** An image attached to a CBT question. */
    case Question = 'question';

    /** A payment receipt photographed on a phone. Must stay legible. */
    case Receipt = 'receipt';

    /** The Super Admin media library. */
    case Library = 'library';

    public function maxEdge(): int
    {
        return match ($this) {
            // Printed at 120px on a report card and shown large on a school's
            // website header; 1600 keeps it crisp on a 2x display at any of them.
            self::Logo => 1600,
            self::Favicon => 512,
            // An ID card prints a portrait at roughly 1.25in tall (~375px at
            // 300dpi); 1600 leaves room for cropping without softening.
            self::Portrait => 1600,
            self::Signature => 1600,
            // Full-bleed sections on wide, high-density screens.
            self::Website, self::Library => 2560,
            self::Question => 2000,
            // A receipt is read, not admired: fine print must survive.
            self::Receipt => 2560,
        };
    }

    /**
     * Upload limit in kilobytes.
     *
     * 10MB covers a 48-50 megapixel phone photograph. Anything larger is almost
     * always a raw or an unprocessed export, and the browser scales large
     * photographs down before sending them anyway (see image-upload-prep.js).
     */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Favicon => 2048,
            self::Website, self::Library, self::Receipt => 15360,
            default => 10240,
        };
    }
}
