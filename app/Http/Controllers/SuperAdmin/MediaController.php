<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Setting;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    private const VIDEO_MIMES = ['mp4', 'mov', 'webm'];

    public function __construct(private readonly UploadStorage $uploads) {}

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
        $previousDisk = $media->disk;
        $previousPath = $media->path;

        $media->update([
            ...$this->storeFileAttributes($file),
            'disk' => 'public',
        ]);

        // The old file goes only once the row points at the new one. It used
        // to be deleted first, so a failed upload left the item pointing at
        // nothing.
        if ($previousPath !== $media->path) {
            $this->uploads->delete($previousDisk, $previousPath);
        }

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
        return Media::create([
            ...$this->storeFileAttributes($file),
            'uploaded_by' => auth()->id(),
            'name' => $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'disk' => 'public',
        ]);
    }

    /**
     * Store an image or a video and describe what was stored.
     *
     * Image or video is decided from the CONTENT (finfo), not the extension
     * the uploader's filename happens to carry, the mimes: rule has already
     * restricted what arrives to the lists at the top of the class. Images go
     * through the processor, so what is recorded is the size and dimensions
     * of the stored file, not of the upload.
     *
     * @return array<string, mixed>
     */
    private function storeFileAttributes(UploadedFile $file): array
    {
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

        if ($isVideo) {
            $path = $this->uploads->storeFile($file, 'public', 'media', 'file');

            return [
                'type' => MediaType::Video,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'width' => null,
                'height' => null,
            ];
        }

        $image = $this->uploads->storeImage($file, 'public', 'media', ImageProfile::Library, 'file');

        return [
            'type' => MediaType::Image,
            'path' => $image->path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $image->mimeType,
            'size' => $image->size,
            'width' => $image->width,
            'height' => $image->height,
        ];
    }
}
