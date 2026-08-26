<x-auth-layout :title="'Link expired - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                <i class="fa-solid fa-hourglass-end text-xl"></i>
            </span>

            <h1 class="mt-5 text-2xl font-bold tracking-tight text-gray-900">This link has expired</h1>

            {{-- One message for expired, already used, and never valid. Telling
                 them apart would confirm to whoever is holding the link which
                 of those it is. --}}
            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-gray-500">
                Password reset links last 30 minutes and can only be used once. Request a new one to continue.
            </p>

            <a
                href="{{ route('admin.password-reset.request') }}"
                class="mt-7 flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                <i class="fa-solid fa-rotate-right"></i> Request a new link
            </a>
        </div>
    </x-auth-card>
</x-auth-layout>
