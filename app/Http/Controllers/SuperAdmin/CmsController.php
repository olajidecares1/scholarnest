<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsController extends Controller
{
    public function index(): View
    {
        return view('super-admin.cms.index', [
            'pages' => Page::orderBy('title')->get(),
            'blogPosts' => BlogPost::with('author')->latest()->get(),
            'testimonials' => Testimonial::whereNull('school_id')->orderBy('sort_order')->get(),
            'faqItems' => FaqItem::orderBy('sort_order')->get(),
            'teamMembers' => TeamMember::orderBy('sort_order')->get(),
        ]);
    }

    public function storePage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);
        $validated['is_published'] = $request->boolean('is_published');

        $page = Page::create($validated);
        AuditLog::record('cms.page.created', "Created page \"{$page->title}\".", $page);

        return back()->with('status', 'Page saved.');
    }

    public function updatePage(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);
        $validated['is_published'] = $request->boolean('is_published');

        $page->update($validated);
        AuditLog::record('cms.page.updated', "Updated page \"{$page->title}\".", $page);

        return back()->with('status', 'Page updated.');
    }

    public function destroyPage(Page $page): RedirectResponse
    {
        $page->delete();
        AuditLog::record('cms.page.deleted', "Deleted page \"{$page->title}\".");

        return back()->with('status', 'Page deleted.');
    }

    public function storeBlogPost(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);
        $validated['is_published'] = $request->boolean('is_published');
        $validated['author_id'] = auth()->id();
        $validated['published_at'] = $validated['is_published'] ? now() : null;

        $post = BlogPost::create($validated);
        AuditLog::record('cms.blog_post.created', "Created blog post \"{$post->title}\".", $post);

        return back()->with('status', 'Blog post saved.');
    }

    public function updateBlogPost(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);
        $validated['is_published'] = $request->boolean('is_published');
        $validated['published_at'] = $validated['is_published'] ? ($blogPost->published_at ?? now()) : null;

        $blogPost->update($validated);
        AuditLog::record('cms.blog_post.updated', "Updated blog post \"{$blogPost->title}\".", $blogPost);

        return back()->with('status', 'Blog post updated.');
    }

    public function destroyBlogPost(BlogPost $blogPost): RedirectResponse
    {
        $blogPost->delete();
        AuditLog::record('cms.blog_post.deleted', "Deleted blog post \"{$blogPost->title}\".");

        return back()->with('status', 'Blog post deleted.');
    }

    public function storeTestimonial(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        Testimonial::create($validated);

        return back()->with('status', 'Testimonial saved.');
    }

    public function updateTestimonial(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $testimonial->update($validated);

        return back()->with('status', 'Testimonial updated.');
    }

    public function destroyTestimonial(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back()->with('status', 'Testimonial deleted.');
    }

    public function storeFaqItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        FaqItem::create($validated);

        return back()->with('status', 'FAQ item saved.');
    }

    public function updateFaqItem(Request $request, FaqItem $faqItem): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $faqItem->update($validated);

        return back()->with('status', 'FAQ item updated.');
    }

    public function destroyFaqItem(FaqItem $faqItem): RedirectResponse
    {
        $faqItem->delete();

        return back()->with('status', 'FAQ item deleted.');
    }

    public function storeTeamMember(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        TeamMember::create($validated);

        return back()->with('status', 'Team member saved.');
    }

    public function updateTeamMember(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $teamMember->update($validated);

        return back()->with('status', 'Team member updated.');
    }

    public function destroyTeamMember(TeamMember $teamMember): RedirectResponse
    {
        $teamMember->delete();

        return back()->with('status', 'Team member deleted.');
    }
}
