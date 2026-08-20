<x-public-site-layout :school="$school" :website="$website" title="News">
    <section class="px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">News & Updates</p>
                <h1 class="mt-2 text-3xl font-extrabold text-gray-900">Latest from {{ $school->name }}</h1>
            </div>

            @if ($posts->isEmpty())
                <p class="mt-10 text-center text-sm text-gray-500">No news posts yet. Check back soon.</p>
            @else
                <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        <a href="{{ route('public.school-news.show', [$school, $post]) }}" class="overflow-hidden rounded-[10px] border border-gray-200 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
                            <div class="h-40 overflow-hidden bg-gray-100">
                                @if ($post->imageUrl())
                                    <img src="{{ $post->imageUrl() }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-gray-300">
                                        <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" /></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="p-5">
                                @if ($post->category)
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-primary-600">{{ $post->category }}</span>
                                @endif
                                <h2 class="mt-1 text-base font-bold text-gray-900">{{ $post->title }}</h2>
                                <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ $post->summary() }}</p>
                                <p class="mt-3 text-xs font-medium text-gray-400">{{ $post->published_at->format('M j, Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
