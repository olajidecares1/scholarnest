<?php

namespace App\Services\Mail;

use App\Models\BrandingImage;
use App\Models\Setting;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * The company's logo at the top of every email.
 *
 * EMBEDDED, NOT LINKED. The image travels inside the email as an inline
 * attachment and the header points at it with a cid: reference. A linked image
 * is hidden by Outlook and many other clients until the reader clicks "show
 * images", so most people would never see it.
 *
 * WHICH LOGO. Whatever logo the Super Admin has uploaded under Themes, read
 * fresh for every email, so changing the logo there changes the next email
 * sent, with nothing to redeploy. The bundled mark is only used while no logo
 * has been uploaded (the same fallback the website and the invoice PDF use),
 * or if the uploaded file cannot be read at all.
 *
 * ANY UPLOADED FORMAT. Outlook and several other clients cannot show WebP, so
 * a WebP (or any other format GD can read) is converted to PNG for the email.
 * The upload itself is left as it is.
 *
 * TRIMMED. A PNG with a transparent border (the bundled mark is half border)
 * is cropped to what is drawn, so the logo fills the size it is given instead
 * of showing as a small shape in an empty square.
 *
 * NEVER IN THE WAY. If the logo cannot be read for any reason the email is
 * still sent, without it.
 */
class MailLogo
{
    /** The name the header refers to, as cid:akademicnest-logo. */
    public const CID = 'akademicnest-logo';

    /** Height, in pixels, the logo is shown at. */
    public const HEIGHT = 64;

    /** The widest a wide uploaded logo is allowed to be. */
    private const MAX_WIDTH = 220;

    private const BUNDLED = 'images/logo-mark.png';

    private const SHOWABLE = ['image/png', 'image/jpeg', 'image/gif'];

    /**
     * The company name shown with the logo: the Site Name from Super Admin
     * settings, so renaming the company there renames it in every email.
     */
    public static function companyName(): string
    {
        try {
            $name = trim((string) Setting::current()->site_name);
        } catch (Throwable) {
            $name = '';
        }

        return $name !== '' ? $name : (string) config('app.name');
    }

    /** @var array{bytes: string, type: string, width: int, height: int}|false|null */
    private array|false|null $image = null;

    /**
     * The logo's bytes, type, and the size to show it at, or null when there
     * is no logo that can be shown.
     *
     * @return array{bytes: string, type: string, width: int, height: int}|null
     */
    public function image(): ?array
    {
        if ($this->image === null) {
            $this->image = $this->resolve() ?? false;
        }

        return $this->image ?: null;
    }

    /**
     * Attach the logo to a message whose HTML asks for it.
     */
    public function embedInto(Email $email): void
    {
        $html = $email->getHtmlBody();

        if (is_resource($html)) {
            $html = stream_get_contents($html);
            $email->html($html, $email->getHtmlCharset());
        }

        if (! is_string($html) || ! str_contains($html, 'cid:'.self::CID)) {
            return;
        }

        foreach ($email->getAttachments() as $part) {
            if ($part->getName() === self::CID) {
                return;
            }
        }

        $image = $this->image();

        if ($image !== null) {
            $email->embed($image['bytes'], self::CID, $image['type']);
        }
    }

    /**
     * @return array{bytes: string, type: string, width: int, height: int}|null
     */
    private function resolve(): ?array
    {
        return $this->uploaded() ?? $this->sized(@file_get_contents(public_path(self::BUNDLED)) ?: null, 'image/png');
    }

    /**
     * @return array{bytes: string, type: string, width: int, height: int}|null
     */
    private function uploaded(): ?array
    {
        try {
            $path = Setting::current()->logo_path;
            $bytes = $path ? BrandingImage::contents($path) : null;

            if (! $bytes) {
                return null;
            }

            $type = BrandingImage::typeFor($path);

            if (! in_array($type, self::SHOWABLE, true)) {
                [$bytes, $type] = [$this->asPng($bytes), 'image/png'];
            }

            return $this->sized($bytes, $type);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{bytes: string, type: string, width: int, height: int}|null
     */
    private function sized(?string $bytes, string $type): ?array
    {
        if ($bytes && $type === 'image/png') {
            $bytes = $this->trimmed($bytes);
        }

        $size = $bytes ? @getimagesizefromstring($bytes) : false;

        if (! $size || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        $height = self::HEIGHT;
        $width = (int) round($size[0] * $height / $size[1]);

        if ($width > self::MAX_WIDTH) {
            $width = self::MAX_WIDTH;
            $height = max(1, (int) round($size[1] * $width / $size[0]));
        }

        return ['bytes' => $bytes, 'type' => $type, 'width' => $width, 'height' => $height];
    }

    /**
     * Any image GD can read, re-encoded as PNG with its transparency, or null
     * when GD cannot read it (an .ico, for one).
     */
    private function asPng(string $bytes): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 9);

        return ob_get_clean() ?: null;
    }

    /**
     * The smallest box holding every pixel that is not (almost) fully
     * transparent, or null when the image is empty or too large to scan.
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function drawnArea(\GdImage $image): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width * $height > 4_000_000) {
            return null;
        }

        [$left, $top, $right, $bottom] = [$width, $height, -1, -1];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // GD alpha runs from 0 (opaque) to 127 (transparent).
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right < 0) {
            return null;
        }

        return ['x' => $left, 'y' => $top, 'width' => $right - $left + 1, 'height' => $bottom - $top + 1];
    }

    /**
     * The PNG with its transparent border cropped away, or unchanged when
     * there is no border, or it cannot be read.
     */
    private function trimmed(string $bytes): string
    {
        try {
            $image = @imagecreatefromstring($bytes);

            if ($image === false) {
                return $bytes;
            }

            $box = $this->drawnArea($image);

            if ($box === null || ($box['width'] === imagesx($image) && $box['height'] === imagesy($image))) {
                return $bytes;
            }

            $cropped = imagecrop($image, $box);

            if ($cropped === false) {
                return $bytes;
            }

            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);

            ob_start();
            imagepng($cropped, null, 9);

            return ob_get_clean() ?: $bytes;
        } catch (Throwable) {
            return $bytes;
        }
    }
}
