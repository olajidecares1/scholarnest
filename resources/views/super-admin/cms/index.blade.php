<x-super-admin-layout page-title="CMS" page-subtitle="Manage platform-wide content.">
    <div class="space-y-6" x-data="{ tab: 'pages' }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
            @foreach (['pages' => 'Pages', 'blog' => 'Blog Posts', 'testimonials' => 'Testimonials', 'faq' => 'FAQ', 'team' => 'Team'] as $key => $label)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    class="rounded-t-[5px] border-b-2 px-4 py-3 text-sm font-semibold transition-colors duration-200"
                    :class="tab === '{{ $key }}' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Pages --}}
        <div x-show="tab === 'pages'" x-data="{ open: false, editing: null }" style="display: none;">
            <div class="flex justify-end">
                <button type="button" @click="editing = null; open = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Page
                </button>
            </div>

            <div class="mt-4 rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr><th class="px-5 py-3 font-semibold">Title</th><th class="px-5 py-3 font-semibold">Slug</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($pages as $page)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $page->title }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">/{{ $page->slug }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $page->is_published ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $page->is_published ? 'Published' : 'Draft' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editing = { id: @js($page->uuid), title: @js($page->title), body: @js($page->body), is_published: {{ $page->is_published ? 'true' : 'false' }} }; open = true" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                        <form method="POST" action="{{ route('super-admin.cms.pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No pages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Page' : 'Add Page'"></h3>
                    <form method="POST" :action="editing ? '{{ route('super-admin.cms.pages.update', ['page' => '__ID__']) }}'.replace('__ID__', editing.id) : '{{ route('super-admin.cms.pages.store') }}'" class="mt-4 space-y-2">
                        @csrf
                        <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                        <x-text-field
                            name="title"
                            label="Title"
                            icon="M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11"
                            helper="Shown as the page heading and in navigation."
                            x-model="editing ? editing.title : ''"
                            required
                        />
                        <x-textarea-field
                            name="body"
                            label="Body"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="The full page content."
                            rows="6"
                            required
                            x-text="editing ? editing.body : ''"
                        />
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="is_published" value="1" x-bind:checked="editing ? editing.is_published : true" class="text-primary-500">
                            Published
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Blog Posts --}}
        <div x-show="tab === 'blog'" x-data="{ open: false, editing: null }" style="display: none;">
            <div class="flex justify-end">
                <button type="button" @click="editing = null; open = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Blog Post
                </button>
            </div>

            <div class="mt-4 rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr><th class="px-5 py-3 font-semibold">Title</th><th class="px-5 py-3 font-semibold">Author</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($blogPosts as $post)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $post->title }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $post->author->name }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $post->is_published ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $post->is_published ? 'Published' : 'Draft' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editing = { id: @js($post->uuid), title: @js($post->title), excerpt: @js($post->excerpt), body: @js($post->body), is_published: {{ $post->is_published ? 'true' : 'false' }} }; open = true" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                        <form method="POST" action="{{ route('super-admin.cms.blog-posts.destroy', $post) }}" onsubmit="return confirm('Delete this blog post?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No blog posts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Blog Post' : 'Add Blog Post'"></h3>
                    <form method="POST" :action="editing ? '{{ route('super-admin.cms.blog-posts.update', ['blogPost' => '__ID__']) }}'.replace('__ID__', editing.id) : '{{ route('super-admin.cms.blog-posts.store') }}'" class="mt-4 space-y-2">
                        @csrf
                        <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                        <x-text-field
                            name="title"
                            label="Title"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="The headline shown on the blog listing."
                            x-model="editing ? editing.title : ''"
                            required
                        />
                        <x-text-field
                            name="excerpt"
                            label="Excerpt"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="A short teaser shown in previews and search results."
                            x-model="editing ? editing.excerpt : ''"
                        />
                        <x-textarea-field
                            name="body"
                            label="Body"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="The full article content."
                            rows="6"
                            required
                            x-text="editing ? editing.body : ''"
                        />
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="is_published" value="1" x-bind:checked="editing ? editing.is_published : false" class="text-primary-500">
                            Published
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Testimonials --}}
        <div x-show="tab === 'testimonials'" x-data="{ open: false, editing: null }" style="display: none;">
            <div class="flex justify-end">
                <button type="button" @click="editing = null; open = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Testimonial
                </button>
            </div>

            <div class="mt-4 rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr><th class="px-5 py-3 font-semibold">Name</th><th class="px-5 py-3 font-semibold">Role</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($testimonials as $testimonial)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $testimonial->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $testimonial->role }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $testimonial->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $testimonial->is_active ? 'Active' : 'Hidden' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editing = { id: @js($testimonial->uuid), name: @js($testimonial->name), role: @js($testimonial->role), quote: @js($testimonial->quote), sort_order: {{ $testimonial->sort_order }}, is_active: {{ $testimonial->is_active ? 'true' : 'false' }} }; open = true" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                        <form method="POST" action="{{ route('super-admin.cms.testimonials.destroy', $testimonial) }}" onsubmit="return confirm('Delete this testimonial?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No testimonials yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Testimonial' : 'Add Testimonial'"></h3>
                    <form method="POST" :action="editing ? '{{ route('super-admin.cms.testimonials.update', ['testimonial' => '__ID__']) }}'.replace('__ID__', editing.id) : '{{ route('super-admin.cms.testimonials.store') }}'" class="mt-4 space-y-2">
                        @csrf
                        <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                        <x-text-field
                            name="name"
                            label="Name"
                            icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                            helper="Who is this testimonial from?"
                            x-model="editing ? editing.name : ''"
                            required
                        />
                        <x-text-field
                            name="role"
                            label="Role"
                            icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                            helper="Their title or school, e.g. Principal, Bright Future Academy."
                            x-model="editing ? editing.role : ''"
                        />
                        <x-textarea-field
                            name="quote"
                            label="Quote"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="What they said about EduNest."
                            rows="4"
                            required
                            x-text="editing ? editing.quote : ''"
                        />
                        <x-text-field
                            name="sort_order"
                            label="Sort Order"
                            type="number"
                            icon="M4 20V10M10 20V4M16 20v-7M20 20v-3"
                            helper="Lower numbers appear first."
                            x-model="editing ? editing.sort_order : 0"
                        />
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="editing ? editing.is_active : true" class="text-primary-500">
                            Active
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- FAQ --}}
        <div x-show="tab === 'faq'" x-data="{ open: false, editing: null }" style="display: none;">
            <div class="flex justify-end">
                <button type="button" @click="editing = null; open = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add FAQ Item
                </button>
            </div>

            <div class="mt-4 rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr><th class="px-5 py-3 font-semibold">Question</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($faqItems as $faqItem)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $faqItem->question }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $faqItem->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $faqItem->is_active ? 'Active' : 'Hidden' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editing = { id: @js($faqItem->uuid), question: @js($faqItem->question), answer: @js($faqItem->answer), sort_order: {{ $faqItem->sort_order }}, is_active: {{ $faqItem->is_active ? 'true' : 'false' }} }; open = true" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                        <form method="POST" action="{{ route('super-admin.cms.faq-items.destroy', $faqItem) }}" onsubmit="return confirm('Delete this FAQ item?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No FAQ items yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit FAQ Item' : 'Add FAQ Item'"></h3>
                    <form method="POST" :action="editing ? '{{ route('super-admin.cms.faq-items.update', ['faqItem' => '__ID__']) }}'.replace('__ID__', editing.id) : '{{ route('super-admin.cms.faq-items.store') }}'" class="mt-4 space-y-2">
                        @csrf
                        <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                        <x-text-field
                            name="question"
                            label="Question"
                            icon="M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01"
                            helper="The question as it will appear to visitors."
                            x-model="editing ? editing.question : ''"
                            required
                        />
                        <x-textarea-field
                            name="answer"
                            label="Answer"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="A clear, concise answer to the question above."
                            rows="4"
                            required
                            x-text="editing ? editing.answer : ''"
                        />
                        <x-text-field
                            name="sort_order"
                            label="Sort Order"
                            type="number"
                            icon="M4 20V10M10 20V4M16 20v-7M20 20v-3"
                            helper="Lower numbers appear first."
                            x-model="editing ? editing.sort_order : 0"
                        />
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="editing ? editing.is_active : true" class="text-primary-500">
                            Active
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Team --}}
        <div x-show="tab === 'team'" x-data="{ open: false, editing: null }" style="display: none;">
            <div class="flex justify-end">
                <button type="button" @click="editing = null; open = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Team Member
                </button>
            </div>

            <div class="mt-4 rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr><th class="px-5 py-3 font-semibold">Name</th><th class="px-5 py-3 font-semibold">Role</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($teamMembers as $member)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $member->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $member->role }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $member->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $member->is_active ? 'Active' : 'Hidden' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editing = { id: @js($member->uuid), name: @js($member->name), role: @js($member->role), bio: @js($member->bio), sort_order: {{ $member->sort_order }}, is_active: {{ $member->is_active ? 'true' : 'false' }} }; open = true" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                        <form method="POST" action="{{ route('super-admin.cms.team-members.destroy', $member) }}" onsubmit="return confirm('Delete this team member?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No team members yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Team Member' : 'Add Team Member'"></h3>
                    <form method="POST" :action="editing ? '{{ route('super-admin.cms.team-members.update', ['teamMember' => '__ID__']) }}'.replace('__ID__', editing.id) : '{{ route('super-admin.cms.team-members.store') }}'" class="mt-4 space-y-2">
                        @csrf
                        <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                        <x-text-field
                            name="name"
                            label="Name"
                            icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                            helper="The team member's full name."
                            x-model="editing ? editing.name : ''"
                            required
                        />
                        <x-text-field
                            name="role"
                            label="Role"
                            icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                            helper="Their title, e.g. Head of Customer Success."
                            x-model="editing ? editing.role : ''"
                        />
                        <x-textarea-field
                            name="bio"
                            label="Bio"
                            icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                            helper="A short introduction shown on the team page."
                            rows="3"
                            x-text="editing ? editing.bio : ''"
                        />
                        <x-text-field
                            name="sort_order"
                            label="Sort Order"
                            type="number"
                            icon="M4 20V10M10 20V4M16 20v-7M20 20v-3"
                            helper="Lower numbers appear first."
                            x-model="editing ? editing.sort_order : 0"
                        />
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="editing ? editing.is_active : true" class="text-primary-500">
                            Active
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-super-admin-layout>
