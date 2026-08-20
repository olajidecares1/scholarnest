<x-public-site-layout :school="$school" :website="$website" title="Gallery">
    <section class="px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Photo Gallery</p>
                <h1 class="mt-2 text-3xl font-extrabold text-gray-900">Life at {{ $school->name }}</h1>
            </div>

            @if ($galleryImages->isEmpty())
                <p class="mt-10 text-center text-sm text-gray-500">No photos have been added yet.</p>
            @else
                <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($galleryImages as $image)
                        <figure class="overflow-hidden rounded-[10px] border border-gray-200">
                            <div class="aspect-square overflow-hidden">
                                <img src="{{ $image->imageUrl() }}" alt="{{ $image->caption }}" class="h-full w-full object-cover transition-transform duration-500 hover:scale-110">
                            </div>
                            @if ($image->caption)
                                <figcaption class="px-3 py-2 text-xs text-gray-600">{{ $image->caption }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $galleryImages->links() }}
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
