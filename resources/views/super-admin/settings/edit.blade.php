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
                    <div>
                        <x-input-label for="site_name" value="Site Name" />
                        <x-text-input id="site_name" name="site_name" type="text" class="mt-1" :value="old('site_name', $settings->site_name)" required />
                        <x-input-error :messages="$errors->get('site_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="support_email" value="Support Email" />
                        <x-text-input id="support_email" name="support_email" type="email" class="mt-1" :value="old('support_email', $settings->support_email)" />
                        <x-input-error :messages="$errors->get('support_email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="support_phone" value="Support Phone" />
                        <x-text-input id="support_phone" name="support_phone" type="text" class="mt-1" :value="old('support_phone', $settings->support_phone)" />
                        <x-input-error :messages="$errors->get('support_phone')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Notification Email</h2>
                <div class="mt-4 space-y-5">
                    <div>
                        <x-input-label for="notification_from_name" value="From Name" />
                        <x-text-input id="notification_from_name" name="notification_from_name" type="text" class="mt-1" :value="old('notification_from_name', $settings->notification_from_name)" required />
                        <x-input-error :messages="$errors->get('notification_from_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notification_from_email" value="From Email" />
                        <x-text-input id="notification_from_email" name="notification_from_email" type="email" class="mt-1" :value="old('notification_from_email', $settings->notification_from_email)" />
                        <x-input-error :messages="$errors->get('notification_from_email')" class="mt-2" />
                    </div>
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
                    <x-input-label for="maintenance_message" value="Maintenance Message" />
                    <textarea
                        id="maintenance_message"
                        name="maintenance_message"
                        rows="3"
                        placeholder="EduNest is currently undergoing scheduled maintenance. Please check back shortly."
                        class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 placeholder:text-gray-400 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                    >{{ old('maintenance_message', $settings->maintenance_message) }}</textarea>
                    <x-input-error :messages="$errors->get('maintenance_message')" class="mt-2" />
                </div>
            </div>

            <button
                type="submit"
                class="flex items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg lg:rounded-[10px]"
            >
                Save Settings
            </button>
        </form>
    </div>
</x-super-admin-layout>
