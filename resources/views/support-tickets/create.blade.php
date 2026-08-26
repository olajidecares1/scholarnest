<x-dashboard-layout page-title="New Support Ticket" page-subtitle="Tell us what you need help with.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
            <form method="POST" action="{{ route('support-tickets.store') }}" class="space-y-2">
                @csrf

                <x-text-field
                    id="subject"
                    name="subject"
                    label="Subject"
                    icon="M4.5 8.5a2 2 0 012-2h11a2 2 0 012 2v7a2 2 0 01-2 2h-11a2 2 0 01-2-2v-7z M4.5 9.5l7.1 4.6a1 1 0 001.1 0l6.8-4.6"
                    helper="A short summary of the issue you're facing."
                    :value="old('subject')"
                    required
                    autofocus
                    placeholder="e.g. Unable to upload payment receipt"
                />

                <x-textarea-field
                    id="message"
                    name="message"
                    label="Message"
                    icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                    helper="Include any steps to reproduce the issue and what you expected to happen."
                    rows="5"
                    required
                    placeholder="Describe the issue in detail..."
                    :value="old('message')"
                />

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
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
