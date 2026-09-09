<x-auth-layout :simple="true" :header="false" title="Find your school">
    <x-auth-card>
        <div class="text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-[10px] bg-primary-600 text-2xl text-white shadow-md">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <h1 class="mt-3 text-xl font-bold text-gray-900">Find your school</h1>
            <p class="mt-2 text-sm text-gray-500">Enter your school name or registered email to continue to its portal.</p>
        </div>

        {{-- Posts back to whichever door it was opened at: /portal, or the
             older token-gated address. --}}
        <form method="POST" action="{{ $token ? route('basic-portal.find', $token) : route('portal.find') }}" class="mt-6 space-y-2">
            @csrf
            <x-honeypot />

            <div>
                <label for="school" class="field-label mb-1">School name or email</label>
                <input
                    id="school"
                    name="school"
                    type="text"
                    value="{{ old('school', $term ?? '') }}"
                    required
                    autofocus
                    autocomplete="organization"
                    placeholder="e.g. Greenfield College, or admin@greenfield.com"
                    class="@error('school') field-invalid @enderror"
                />
                @error('school')
                    <p class="mt-1 text-[11px] font-medium leading-[1.45] text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Continue
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </form>

        {{-- Shown when the typed name matched more than one school. Picking for
             them would risk sending someone into the wrong school's portal. --}}
        @if (isset($matches) && $matches->isNotEmpty())
            <div class="mt-6">
                <p class="text-sm font-semibold text-gray-700">
                    {{ $matches->count() }} schools match &ldquo;{{ $term }}&rdquo;. Which is yours?
                </p>

                <div class="mt-3 space-y-2">
                    @foreach ($matches as $match)
                        <a
                            {{-- The portal hub, for the same reason the single
                                 match redirects there: somebody who asked for
                                 a portal wants the portal, not the school's
                                 public website.

                                 The model, not the slug: the route binds on
                                 portal_key, so a slug string would build an
                                 address that no longer resolves. --}}
                            href="{{ route('portal.index', $match) }}"
                            class="group flex items-center gap-3 rounded-[8px] border border-gray-200 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md"
                        >
                            @if ($match->logoUrl())
                                <img src="{{ $match->logoUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-[8px] object-cover">
                            @else
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600">
                                    <i class="fa-solid fa-school"></i>
                                </span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-gray-900">{{ $match->name }}</span>
                                {{-- The school code is what actually separates two
                                     schools with similar names, and it is meant to
                                     be shared - unlike the slug, which is generated. --}}
                                <span class="block truncate text-xs text-gray-500">School code: {{ $match->school_code }}</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-colors duration-200 group-hover:text-primary-400"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-6 text-center text-xs text-gray-500">
            Not sure of the exact name? Ask your school for the name they registered with AkademicNest.
        </p>
    </x-auth-card>
</x-auth-layout>
