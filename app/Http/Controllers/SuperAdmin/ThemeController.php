<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BrandingImage;
use App\Models\Setting;
use App\Support\StoredUpload;
use App\Support\ThemePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function edit(): View
    {
        return view('super-admin.themes.edit', [
            'settings' => Setting::current(),
            'presets' => ThemePreset::PRESETS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme_preset' => ['required', 'string', 'in:'.implode(',', array_keys(ThemePreset::PRESETS))],
        ]);

        $settings = Setting::current();
        $settings->update($validated);

        AuditLog::record('theme.updated', 'Changed platform color theme to '.ThemePreset::label($validated['theme_preset']).'.', $settings);

        return back()->with('status', 'Theme updated.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            // svg was listed here but never accepted: the `image` rule beside
            // it refuses SVG unless allow_svg is passed. Advertising a format
            // that is silently rejected is how somebody spends an afternoon
            // wondering why their logo will not upload.
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [
            'logo.max' => 'The logo may not be larger than 2MB.',
        ]);

        $settings = Setting::current();

        $previous = $settings->logo_path;

        $file = $request->file('logo');
        $path = $this->storeBrandingImage($file, 'logo');

        $settings->update(['logo_path' => $path]);

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
            BrandingImage::forget($previous);
        }

        AuditLog::record('theme.logo_updated', 'Updated platform logo.', $settings);

        return back()->with('status', 'Logo updated.');
    }

    /**
     * The platform favicon.
     *
     * The rules here are `mimetypes`, not `image` + `mimes`, and that is the
     * whole reason this feature appeared broken. The form offers PNG or ICO
     * and `accept=".png,.ico"`, but `image` refuses ICO outright - it admits
     * jpg, jpeg, png, bmp, gif, webp and nothing else - so every .ico upload
     * failed validation no matter what sat beside it. The same trap the logo
     * rules above carry a note about, with `svg`.
     *
     * `mimetypes` also reads the file's actual content rather than trusting
     * the name it arrived under, so a .png that is really something else is
     * refused too.
     */
    public function updateFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => [
                'required',
                'file',

                // image/x-icon is what most browsers and editors write;
                // image/vnd.microsoft.icon is the registered name, and which
                // one finfo reports depends on the platform's magic database.
                'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon',

                // 2MB, matching the logo above rather than the 512KB this
                // used to carry. That was the tightest limit in the whole
                // application - half what a SCHOOL's own favicon is allowed -
                // and it is genuinely too small: a .ico holding the usual
                // 16/32/48/64/128/256px set runs to several hundred KB, and a
                // 512px PNG passes 512KB on its own. The file is stored once
                // and served from cache, so there is nothing to be gained by
                // being mean about it.
                'max:2048',
            ],
        ], [
            'favicon.mimetypes' => 'The favicon must be a PNG or ICO file.',
            'favicon.max' => 'The favicon may not be larger than 2MB.',
        ]);

        $settings = Setting::current();

        $previous = $settings->favicon_path;

        $file = $request->file('favicon');
        $path = $this->storeBrandingImage($file, 'favicon');

        $settings->update(['favicon_path' => $path]);

        // Deleted only once the replacement is stored and recorded. Deleting
        // first meant a failed write left the setting pointing at a file that
        // no longer existed, and every page in the platform asking for a
        // favicon that 404s.
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
            BrandingImage::forget($previous);
        }

        AuditLog::record('theme.favicon_updated', 'Updated platform favicon.', $settings);

        return back()->with('status', 'Favicon updated.');
    }

    /**
     * Store a logo or favicon and return the path the setting records.
     *
     * THE DATABASE COPY IS THE ONE THAT COUNTS. Pages load these images from
     * BrandingImageController, which reads the database, because in production
     * the public disk is a directory that is not served at /storage and is
     * wiped by every deploy - an upload that lived only there reported success
     * and never appeared anywhere. The disk copy is still written, for the
     * subscription invoice PDF on a server where it survives, but nothing
     * depends on the write succeeding.
     */
    private function storeBrandingImage(UploadedFile $file, string $kind): string
    {
        $path = BrandingImage::DIRECTORY.'/'.StoredUpload::name($file, $kind.'-'.Str::random(8));

        Storage::disk('public')->putFileAs(BrandingImage::DIRECTORY, $file, basename($path));

        BrandingImage::remember($path, $file);

        return $path;
    }
}
