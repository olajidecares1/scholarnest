<x-dashboard-layout page-title="New Support Ticket" page-subtitle="Tell us what you need help with.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
            <form method="POST" action="{{ route('support-tickets.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="subject" value="Subject" />
                    <x-text-input id="subject" name="subject" type="text" class="mt-1" :value="old('subject')" required autofocus placeholder="e.g. Unable to upload payment receipt" />
                    <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="message" value="Message" />
                    <textarea
                        id="message"
                        name="message"
                        rows="5"
                        required
                        placeholder="Describe the issue in detail..."
                        class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 placeholder:text-gray-400 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 lg:rounded-[10px]"
                    >{{ old('message') }}</textarea>
                    <x-input-error :messages="$errors->get('message')" class="mt-2" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 lg:rounded-[10px]"
                    >
                        Submit Ticket
                    </button>
                    <a href="{{ route('support-tickets.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-dashboard-layout>
