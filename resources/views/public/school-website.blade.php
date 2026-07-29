<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $school->name }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white text-gray-900 antialiased">
        <header
            class="relative flex min-h-[420px] items-center justify-center overflow-hidden bg-gray-900 text-center text-white"
            @if ($website->heroImageUrl()) style="background-image: url('{{ $website->heroImageUrl() }}'); background-size: cover; background-position: center;" @endif
        >
            <div class="absolute inset-0 bg-gray-900/60"></div>
            <div class="relative z-10 mx-auto max-w-3xl px-6 py-20">
                <h1 class="text-3xl font-extrabold sm:text-5xl">{{ $website->hero_title ?: $school->name }}</h1>
                @if ($website->hero_subtitle)
                    <p class="mt-4 text-lg text-gray-200">{{ $website->hero_subtitle }}</p>
                @endif
            </div>
        </header>

        @if ($website->about_text)
            <section class="mx-auto max-w-3xl px-6 py-16 text-center">
                <h2 class="text-sm font-bold uppercase tracking-wide text-blue-600">About Us</h2>
                <p class="mt-4 whitespace-pre-line text-base leading-relaxed text-gray-700">{{ $website->about_text }}</p>
            </section>
        @endif

        @if ($galleryImages->isNotEmpty())
            <section class="mx-auto max-w-5xl px-6 py-16">
                <h2 class="text-center text-sm font-bold uppercase tracking-wide text-blue-600">Gallery</h2>
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($galleryImages as $image)
                        <figure class="overflow-hidden rounded-[10px] border border-gray-200">
                            <img src="{{ $image->imageUrl() }}" alt="{{ $image->caption }}" class="h-48 w-full object-cover">
                            @if ($image->caption)
                                <figcaption class="px-3 py-2 text-xs text-gray-600">{{ $image->caption }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($website->contact_email || $website->contact_phone || $website->contact_address)
            <section class="border-t border-gray-100 bg-gray-50 px-6 py-16 text-center">
                <h2 class="text-sm font-bold uppercase tracking-wide text-blue-600">Get in Touch</h2>
                <dl class="mx-auto mt-6 flex max-w-xl flex-col items-center gap-2 text-sm text-gray-700">
                    @if ($website->contact_address)
                        <div>{{ $website->contact_address }}</div>
                    @endif
                    @if ($website->contact_phone)
                        <div>{{ $website->contact_phone }}</div>
                    @endif
                    @if ($website->contact_email)
                        <div><a href="mailto:{{ $website->contact_email }}" class="font-semibold text-blue-600 hover:text-blue-700">{{ $website->contact_email }}</a></div>
                    @endif
                </dl>

                @if ($website->facebook_url || $website->twitter_url || $website->instagram_url)
                    <div class="mt-6 flex justify-center gap-4 text-sm font-semibold text-gray-600">
                        @if ($website->facebook_url)
                            <a href="{{ $website->facebook_url }}" target="_blank" rel="noopener" class="hover:text-blue-600">Facebook</a>
                        @endif
                        @if ($website->twitter_url)
                            <a href="{{ $website->twitter_url }}" target="_blank" rel="noopener" class="hover:text-blue-600">Twitter</a>
                        @endif
                        @if ($website->instagram_url)
                            <a href="{{ $website->instagram_url }}" target="_blank" rel="noopener" class="hover:text-blue-600">Instagram</a>
                        @endif
                    </div>
                @endif
            </section>
        @endif

        <footer class="px-6 py-8 text-center text-xs text-gray-400">
            &copy; {{ now()->year }} {{ $school->name }}. Powered by EduNest.
        </footer>
    </body>
</html>
