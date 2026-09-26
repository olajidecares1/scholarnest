<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <small class="block mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </small>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-2">
        @csrf
        @method('patch')

        {{-- The same field the student and staff forms use, so every
             photograph on the platform arrives by one path. --}}
        <x-photo-field
            name="photo"
            id="account_photo"
            label="Profile Photograph"
            :existing="$user->photoUrl()"
            helper="Optional. Take one with the camera or upload a JPG, PNG or WebP up to 10MB. Leave blank to keep the one you have."
        />

        <x-text-field
            id="name"
            name="name"
            label="Name"
            icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
            helper="Your full name, as shown across AkademicNest."
            :value="old('name', $user->name)"
            required
            autofocus
            autocomplete="name"
        />

        <div>
            <x-text-field
                id="email"
                name="email"
                label="Email"
                icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                helper="Used to sign in and receive notifications."
                type="email"
                :value="old('email', $user->email)"
                required
                autocomplete="username"
            />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <small
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="block text-sm text-gray-600"
                >{{ __('Saved.') }}</small>
            @endif
        </div>
    </form>
</section>
