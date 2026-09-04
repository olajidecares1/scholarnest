<x-guardian-layout page-title="Settings" page-subtitle="Keep your contact details up to date">
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Your Contact Details</h2>
            <p class="field-hint mt-0.5">
                Yours to keep current &mdash; you are the one who knows when they change.
                Your school is notified so its records stay in step with yours.
            </p>

            <form method="POST" action="{{ route('guardian.settings.update-profile', $school) }}" class="mt-4 space-y-2">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-4">
                    @if ($guardian->photoUrl())
                        <img src="{{ $guardian->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ Str::of($guardian->name)->substr(0, 1)->upper() }}</span>
                    @endif
                    <p class="field-hint">Your photo can only be changed by the school office.</p>
                </div>

                <x-text-field name="name" label="Full Name" :value="$guardian->name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" required />
                <x-text-field name="email" type="email" label="Email Address" :value="$guardian->email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" />
                <x-text-field name="phone" type="tel" label="Phone Number" :value="$guardian->phone" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" />

                {{-- Named rather than simply absent. Which children are linked
                     to this account is the most consequential thing on it - it
                     decides whose results this parent can read - so it is the
                     school's alone, and a parent should learn that here rather
                     than by hunting for a control that does not exist. --}}
                <p class="field-hint">
                    Your Parent ID and the children linked to your account are the school&rsquo;s records to
                    maintain. Contact the school office if a child needs adding or removing.
                </p>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700">Update</button>
                </div>
            </form>
        </div>

        {{-- No password form, and no route behind one: the School Admin is the
             sole authority on credentials. --}}
        <div class="flex items-start gap-3 rounded-[10px] border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
            <i class="fa-solid fa-lock mt-0.5 text-gray-400"></i>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Password</h2>
                <p class="field-hint mt-0.5">
                    Passwords are set by your school office. If you need yours changed or have forgotten it,
                    ask them to issue you a new one &mdash; they can do it straight away.
                </p>
            </div>
        </div>
    </div>
</x-guardian-layout>
