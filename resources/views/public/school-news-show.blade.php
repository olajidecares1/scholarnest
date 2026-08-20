<x-public-site-layout :school="$school" :website="$website" :title="$post->title">
    <article class="px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('public.school-news.index', $school) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 hover:text-primary-700">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 12H5M11 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to News
            </a>

            @if ($post->category)
                <span class="mt-4 inline-block text-[10px] font-bold uppercase tracking-wide text-primary-600">{{ $post->category }}</span>
            @endif
            <h1 class="mt-2 text-3xl font-extrabold text-gray-900">{{ $post->title }}</h1>
            <p class="mt-2 text-xs font-medium text-gray-400">{{ $post->published_at->format('F j, Y') }}</p>

            @if ($post->imageUrl())
                <img src="{{ $post->imageUrl() }}" class="mt-6 w-full rounded-[10px] object-cover" style="max-height: 420px;">
            @endif

            <div class="mt-6 whitespace-pre-line text-[15px] leading-relaxed text-gray-700">{{ $post->body }}</div>
        </div>

        @if ($otherPosts->isNotEmpty())
            <div class="mx-auto mt-14 max-w-3xl border-t border-gray-100 pt-10">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">More News</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($otherPosts as $other)
                        <a href="{{ route('public.school-news.show', [$school, $other]) }}" class="flex items-center justify-between gap-3 rounded-[8px] p-2 transition-colors duration-150 hover:bg-gray-50">
                            <span class="text-sm font-semibold text-gray-800">{{ $other->title }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ $other->published_at->format('M j') }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </article>
</x-public-site-layout>
