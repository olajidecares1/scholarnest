<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Setting;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    private const VIDEO_MIMES = ['mp4', 'mov', 'webm'];

    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function index(Request $request): View
    {
        $query = Media::query()->with('uploadedBy')->latest();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $settings = Setting::current();

        return view('super-admin.media.index', [
            'media' => $query->paginate(12)->withQueryString(),
            'stats' => [
                'images' => Media::where('type', MediaType::Image)->count(),
                'videos' => Media::where('type', MediaType::Video)->count(),
                'totalSize' => Media::sum('size'),
            ],
            'settings' => $settings,
            'imageLibrary' => Media::where('type', MediaType::Image)->orderBy('name')->get(),
            'videoLibrary' => Media::where('type', MediaType::Video)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', [...self::IMAGE_MIMES, ...self::VIDEO_MIMES]), 'max:51200'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->storeUploadedFile($request->file('file'), $validated['name'] ?? null);

        AuditLog::record('media.uploaded', "Uploaded {$media->type->label()} \"{$media->name}\".", $media);

        return back()->with('status', "\"{$media->name}\" uploaded successfully.");
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $media->update($validated);
        AuditLog::record('media.renamed', "Renamed media to \"{$media->name}\".", $media);

        return back()->with('status', 'Media renamed successfully.');
    }

    public function replace(Request $request, Media $media): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', [...self::IMAGE_MIMES, ...self::VIDEO_MIMES]), 'max:51200'],
        ]);

        $file = $validated['file'] ?? $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $type = in_array($extension, self::VIDEO_MIMES, true) ? MediaType::Video : MediaType::Image;

        Storage::disk($media->disk)->delete($media->path);

        $path = $file->storeAs('media', (string) Str::uuid().'.'.$extension, 'public');
        $width = null;
        $height = null;
        $size = Storage::disk('public')->size($path);

        if ($type === MediaType::Image) {
            $optimized = $this->optimizer->optimize(Storage::disk('public')->path($path), $file->getMimeType());
            $width = $optimized['width'] ?: null;
            $height = $optimized['height'] ?: null;
            $size = $optimized['size'] ?: $size;
        }

        $media->update([
            'type' => $type,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ]);

        AuditLog::record('media.replaced', "Replaced file for media \"{$media->name}\".", $media);

        return back()->with('status', "\"{$media->name}\" replaced successfully.");
    }

    public function destroy(Media $media): RedirectResponse
    {
        $settings = Setting::current();
        $updates = [];

        if ($settings->login_background_media_id === $media->id) {
            $updates['login_background_media_id'] = null;
        }

        if ($settings->register_background_media_id === $media->id) {
            $updates['register_background_media_id'] = null;
        }

        if ($updates !== []) {
            $settings->update($updates);
        }

        Storage::disk($media->disk)->delete($media->path);

        $name = $media->name;
        $media->delete();

        AuditLog::record('media.deleted', "Deleted media \"{$name}\".");

        return back()->with('status', "\"{$name}\" deleted successfully.");
    }

    public function updateBackgrounds(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_background_media_id' => ['nullable', 'integer', 'exists:'.Media::class.',id'],
            'register_background_media_id' => ['nullable', 'integer', 'exists:'.Media::class.',id'],
        ]);

        $settings = Setting::current();
        $settings->update([
            'login_background_media_id' => $validated['login_background_media_id'] ?? null,
            'register_background_media_id' => $validated['register_background_media_id'] ?? null,
        ]);

        AuditLog::record('media.backgrounds_updated', 'Updated login/register page backgrounds.', $settings);

        return back()->with('status', 'Backgrounds updated successfully.');
    }

    private function storeUploadedFile(UploadedFile $file, ?string $name): Media
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $type = in_array($extension, self::VIDEO_MIMES, true) ? MediaType::Video : MediaType::Image;

        $path = $file->storeAs('media', (string) Str::uuid().'.'.$extension, 'public');

        $width = null;
        $height = null;
        $size = Storage::disk('public')->size($path);

        if ($type === MediaType::Image) {
            $optimized = $this->optimizer->optimize(Storage::disk('public')->path($path), $file->getMimeType());
            $width = $optimized['width'] ?: null;
            $height = $optimized['height'] ?: null;
            $size = $optimized['size'] ?: $size;
        }

        return Media::create([
            'uploaded_by' => auth()->id(),
            'name' => $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'type' => $type,
            'disk' => 'public',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ]);
    }
}
