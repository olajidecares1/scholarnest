<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Services\ImageOptimizer;
use App\Support\StoredUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NewsController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $posts = $school->newsPosts()
            ->orderByDesc('published_at')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.news.index', [
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
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        $path = $file->storeAs('news', StoredUpload::name($file), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    private function authorizePost(NewsPost $post): void
    {
        $this->authorizeSchoolOwnership($post);
    }
}
