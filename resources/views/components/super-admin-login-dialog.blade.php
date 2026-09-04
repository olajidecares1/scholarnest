@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path
        ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path)
        : asset('images/logo-icon-dark.png');
@endphp

{{-- The hidden Super Admin sign-in.

     Revealed by the logo click sequence. The surrounding element must provide
     the Alpine scope this reads - `open`, `close()` and the `superAdminLogin`
     ref - which is the same scope that counts the clicks.

     Hiding it is convenience, not protection: the endpoint it posts to
     re-checks the credentials, the Super Admin role, the account status and
     both rate limiters however the request arrives. --}}
<div
    x-cloak
    x-show="open"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-[#0F2A5C]/60 p-4 backdrop-blur-sm sm:items-center"
    @click.self="close()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="super-admin-login-title"
>
    {{-- my-auto centres it when it fits and lets it scroll from the top when
         it does not, which is what a small phone in landscape needs. --}}
    <div
        class="my-auto w-full max-w-[340px] rounded-[14px] bg-white p-7 shadow-[0_24px_60px_-12px_rgba(15,42,92,0.35)]"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
    >
        <button
            type="button"
            @click="close()"
            class="float-right -mr-2 -mt-2 rounded-[8px] p-2 text-[#9AAAC4] transition hover:bg-gray-50 hover:text-[#5B7099]"
            aria-label="Close"
        >
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <div class="text-center">
            <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'ScholarNest') }}" class="mx-auto h-10 w-10 rounded-[9px]">

            <h2 id="super-admin-login-title" class="mt-3.5 text-[17px] font-bold tracking-tight text-[#0F2A5C]">
                ScholarNest
            </h2>
            <p class="mt-0.5 flex items-center justify-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary-500">
                <i class="fa-solid fa-user-shield text-[10px]"></i> ScholarNest Team
            </p>
        </div>

        @if ($errors->has('login'))
            <div class="mt-5 flex items-start gap-2 rounded-[8px] bg-red-50 px-3 py-2.5 text-[11.5px] font-medium leading-snug text-red-700">
                <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('super-admin.login') }}"
            class="mt-6 space-y-2"
            x-data="{ submitting: false, showPassword: false }"
            @submit="submitting = true"
        >
            @csrf

            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#9AAAC4]">
                    <i class="fa-solid fa-user text-[13px]"></i>
                </span>

                <input
                    id="super_admin_login"
                    x-ref="superAdminLogin"
                    name="login"
                    type="text"
                    value="{{ old('login') }}"
                    required
                    autocomplete="username"
                    placeholder="Email or username"
                    aria-label="Email or username"
                    class="w-full pl-9 pr-3"
                >
            </div>

            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#9AAAC4]">
                    <i class="fa-solid fa-lock text-[13px]"></i>
                </span>

                <input
                    id="super_admin_password"
                    name="password"
                    :type="showPassword ? 'text' : 'password'"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                    aria-label="Password"
                    class="w-full pl-9 pr-9"
                >

                <button
                    type="button"
                    @click="showPassword = ! showPassword"
                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#9AAAC4] transition hover:text-[#5B7099]"
                >
                    <i class="fa-solid text-[13px]" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>

            <div class="flex items-center justify-between gap-3 pt-0.5">
                <label class="flex items-center gap-2 text-[11.5px] text-[#5B7099]">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="w-[14px] text-primary-500"
                    >
                    Remember me
                </label>

                {{-- The same reset flow School Admins use: both roles are on
                     the "web" guard, so one implementation serves them both. --}}
                {{-- password.request, not the old admin.password-reset.request.
                     Both flows reset a web-guard account; the parallel one was
                     deleted because nothing linked to it except this dialog,
                     and the sign-in page pointed at the other. --}}
                <a href="{{ route('password.request') }}" class="text-[11.5px] font-semibold text-primary-500 transition hover:text-primary-600">
                    Forgot password?
                </a>
            </div>

            <button
                type="submit"
                :disabled="submitting"
                class="!mt-5 flex h-[40px] w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 text-[13px] font-bold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span x-show="! submitting" class="flex items-center gap-2">
                    <i class="fa-solid fa-right-to-bracket text-[12px]"></i> Sign In
                </span>
                <span x-show="submitting" x-cloak class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-notch fa-spin text-[12px]"></i> Signing in&hellip;
                </span>
            </button>
        </form>

        <p class="mt-5 flex items-center justify-center gap-1.5 border-t border-gray-100 pt-4 text-[10.5px] text-[#8194B3]">
            <i class="fa-solid fa-shield-halved text-[10px]"></i>
            Restricted access &middot; All sign-ins are logged
        </p>
    </div>
</div>
