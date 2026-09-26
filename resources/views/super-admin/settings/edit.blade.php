<x-super-admin-layout page-title="System Settings" page-subtitle="Configure platform-wide settings and maintenance mode.">
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('super-admin.settings.update') }}" class="space-y-2">
            @csrf
            @method('PUT')

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">General</h2>
                <div class="mt-4 space-y-5">
                    <x-text-field
                        id="site_name"
                        name="site_name"
                        label="Site Name"
                        icon="M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11"
                        helper="Shown in page titles and platform emails."
                        :value="old('site_name', $settings->site_name)"
                        required
                    />

                    <x-text-field
                        id="support_email"
                        name="support_email"
                        label="Support Email"
                        type="email"
                        icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                        helper="Where schools can reach the AkademicNest team."
                        :value="old('support_email', $settings->support_email)"
                    />

                    <x-text-field
                        id="support_phone"
                        name="support_phone"
                        label="Support Phone"
                        type="tel"
                        icon="M6.5 3.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1.5 1.5 0 01-1.6 1.5A16.5 16.5 0 015 5.1a1.5 1.5 0 011.5-1.6z"
                        helper="Optional phone number displayed to schools."
                        :value="old('support_phone', $settings->support_phone)"
                    />
                </div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Notification Email</h2>
                <div class="mt-4 space-y-5">
                    <x-text-field
                        id="notification_from_name"
                        name="notification_from_name"
                        label="From Name"
                        icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                        helper="The sender name recipients see on platform emails."
                        :value="old('notification_from_name', $settings->notification_from_name)"
                        required
                    />

                    <x-text-field
                        id="notification_from_email"
                        name="notification_from_email"
                        label="From Email"
                        type="email"
                        icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                        helper="The reply-to address for outgoing notifications."
                        :value="old('notification_from_email', $settings->notification_from_email)"
                    />
                </div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]" x-data="{ maintenance: {{ old('maintenance_mode', $settings->maintenance_mode) ? 'true' : 'false' }} }">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Maintenance Mode</h2>
                <small class="field-hint mt-1">While enabled, school admins and visitors see a maintenance page. AkademicNest Teams can still access the platform.</small>

                <div class="mt-4 flex items-center justify-between rounded-[5px] border border-gray-200 p-3 dark:border-gray-700 lg:rounded-[10px]">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Enable maintenance mode</span>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="maintenance"
                        @click="maintenance = !maintenance"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-300 ease-out"
                        :class="maintenance ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600'"
                    >
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-300 ease-out" :class="maintenance ? 'translate-x-6' : 'translate-x-1'"></span>
                    </button>
                    <input type="hidden" name="maintenance_mode" :value="maintenance ? 1 : 0">
                </div>

                <div class="mt-4">
                    <x-textarea-field
                        id="maintenance_message"
                        name="maintenance_message"
                        label="Maintenance Message"
                        icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                        helper="Shown to visitors while maintenance mode is on."
                        rows="3"
                        placeholder="AkademicNest is currently undergoing scheduled maintenance. Please check back shortly."
                        :value="old('maintenance_message', $settings->maintenance_message)"
                    />
                </div>
            </div>

            <button
                type="submit"
                class="btn flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
            >
                Save Settings
            </button>
        </form>

        {{-- Proving that this server can deliver email. Welcome emails, invoices,
             top-up confirmations and password reset codes all depend on it. --}}
        <div id="email-delivery" class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                <i class="fa-solid fa-envelope-circle-check text-primary-600" aria-hidden="true"></i> Email delivery
            </h2>
            <small class="field-hint mt-0.5">These settings come from the server environment (MAIL_*). The password is never shown.</small>

            @if ($mailProblem)
                <div class="mt-3 flex items-start gap-2 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                    <span>{{ $mailProblem }}</span>
                </div>
            @endif

            <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                @foreach ($mailSettings as $label => $value)
                    <div class="flex justify-between gap-3 rounded-[8px] bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="break-all text-right font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if (session('mail_test_status'))
                <div class="mt-3 flex items-start gap-2 rounded-[8px] border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300" role="status">
                    <i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i>
                    <span>{{ session('mail_test_status') }}</span>
                </div>
            @endif

            @if (session('mail_test_error'))
                <div class="mt-3 flex items-start gap-2 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300" role="alert">
                    <i class="fa-solid fa-circle-xmark mt-0.5" aria-hidden="true"></i>
                    <span class="break-words">{{ session('mail_test_error') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('super-admin.settings.test-email') }}#email-delivery" class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end" x-data="{ sending: false }" @submit="sending = true">
                @csrf
                <x-text-field
                    name="test_email"
                    type="email"
                    label="Send a test email to"
                    icon="fa-at"
                    :value="old('test_email', auth()->user()->email)"
                    required
                />
                <button type="submit" :disabled="sending" class="btn inline-flex h-[var(--field-height)] items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span x-show="! sending">Send test email</span>
                    <span x-show="sending" x-cloak>Sending&hellip;</span>
                </button>
            </form>
        </div>
    </div>
</x-super-admin-layout>
