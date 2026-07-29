@php
    $publicUrl = route('public.school-website', $school);
@endphp

<x-dashboard-layout page-title="Website" page-subtitle="Build your school's public website.">
    <div class="space-y-6" x-data="{ galleryOpen: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Publication Status</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $website->is_published ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $website->is_published ? 'Live' : 'Unpublished' }}
                        </span>
                    </div>
                    @if ($website->is_published)
                        <a href="{{ $publicUrl }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:text-blue-700">{{ $publicUrl }}</a>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Publish to make your website visible at {{ $publicUrl }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('website.publish') }}">
                    @csrf
                    <button type="submit" class="rounded-[8px] {{ $website->is_published ? 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700' : 'bg-blue-600 text-white hover:bg-blue-700' }} px-4 py-2 text-sm font-semibold transition-all duration-200">
                        {{ $website->is_published ? 'Unpublish' : 'Publish Website' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Website Content</h2>
            <form method="POST" action="{{ route('website.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-text-field name="hero_title" label="Hero Title" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" value="{{ $website->hero_title }}" required />
                    <x-text-field name="hero_subtitle" label="Hero Subtitle" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->hero_subtitle }}" helper="Optional." />
                </div>

                <div>
                    <input type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hero banner image (optional). Leave blank to keep the existing one.</p>
                    @if ($website->heroImageUrl())
                        <img src="{{ $website->heroImageUrl() }}" class="mt-2 h-24 w-full max-w-sm rounded-[8px] object-cover">
                    @endif
                </div>

                <x-textarea-field name="about_text" label="About Your School" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="4" value="{{ $website->about_text }}" />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-text-field name="contact_email" label="Contact Email" type="email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" value="{{ $website->contact_email }}" helper="Optional." />
                    <x-text-field name="contact_phone" label="Contact Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" value="{{ $website->contact_phone }}" helper="Optional." />
                    <x-text-field name="contact_address" label="Address" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" value="{{ $website->contact_address }}" helper="Optional." />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-text-field name="facebook_url" label="Facebook URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->facebook_url }}" helper="Optional." />
                    <x-text-field name="twitter_url" label="Twitter / X URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->twitter_url }}" helper="Optional." />
                    <x-text-field name="instagram_url" label="Instagram URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->instagram_url }}" helper="Optional." />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Website Content</button>
                </div>
            </form>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Gallery</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Photos shown on your public website.</p>
                </div>
                <button
                    type="button"
                    @click="galleryOpen = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Image
                </button>
            </div>

            <div class="p-6">
                @if ($galleryImages->isEmpty())
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No gallery images yet. Click "Add Image" to upload one.</p>
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($galleryImages as $image)
                            <div class="group relative overflow-hidden rounded-[8px] border border-gray-200 dark:border-gray-700">
                                <img src="{{ $image->imageUrl() }}" class="h-32 w-full object-cover">
                                @if ($image->caption)
                                    <p class="truncate bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700/50 dark:text-gray-300">{{ $image->caption }}</p>
                                @endif
                                <form method="POST" action="{{ route('website.gallery.destroy', $image) }}" onsubmit="return confirm('Remove this image?');" class="absolute right-1.5 top-1.5">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-red-600 opacity-0 shadow-sm transition-opacity duration-150 group-hover:opacity-100">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Add Gallery Image modal --}}
        <div x-show="galleryOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="galleryOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Gallery Image</h3>
                <form method="POST" action="{{ route('website.gallery.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                    </div>
                    <x-text-field name="caption" label="Caption" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Optional." />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="galleryOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
