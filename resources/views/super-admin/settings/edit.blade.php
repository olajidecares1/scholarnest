<x-super-admin-layout page-title="System Settings" page-subtitle="Configure platform-wide settings and maintenance mode.">
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('super-admin.settings.update') }}" class="space-y-6">
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
                        helper="Where schools can reach the EduNest team."
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
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">While enabled, school admins and visitors see a maintenance page. Super Admins can still access the platform.</p>

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
                        placeholder="EduNest is currently undergoing scheduled maintenance. Please check back shortly."
                        :value="old('maintenance_message', $settings->maintenance_message)"
                    />
                </div>
            </div>

            <button
                type="submit"
                class="flex items-center justify-center gap-2 rounded-[2px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
            >
                Save Settings
            </button>
        </form>
    </div>
</x-super-admin-layout>
