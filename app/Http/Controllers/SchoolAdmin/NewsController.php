<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Rules\UploadedImage;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NewsController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(private readonly UploadStorage $uploads) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $posts = $school->newsPosts()
            ->orderByDesc('published_at')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.news.index', [
            'website' => $school->website,
            'posts' => $posts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $imagePath = $this->storeImage($request);

        $post = $school->newsPosts()->create([
            ...collect($validated)->except('image')->all(),
            'image_path' => $imagePath,
            'is_published' => $request->boolean('is_published', true),
            'published_at' => $validated['published_at'] ?? now(),
        ]);

        return back()->with('status', "\"{$post->title}\" was published.");
    }

    public function update(Request $request, NewsPost $post): RedirectResponse
    {
        $this->authorizePost($post);

        $validated = $request->validate($this->rules());
        $imagePath = $this->storeImage($request);

        $post->update([
            ...collect($validated)->except('image')->all(),
            'image_path' => $imagePath ?: $post->image_path,
            'is_published' => $request->boolean('is_published', true),
            'published_at' => $validated['published_at'] ?? $post->published_at,
        ]);

        return back()->with('status', "\"{$post->title}\" was updated.");
    }

    public function destroy(NewsPost $post): RedirectResponse
    {
        $this->authorizePost($post);

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }

        $title = $post->title;
        $post->delete();

        return back()->with('status', "\"{$title}\" was removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:100'],
            'published_at' => ['nullable', 'date'],
            'image' => UploadedImage::rules(ImageProfile::Website),
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $this->uploads->storeImage($request->file('image'), 'public', 'news', ImageProfile::Website, 'image')->path;
    }

    private function authorizePost(NewsPost $post): void
    {
        $this->authorizeSchoolOwnership($post);
    }
}
