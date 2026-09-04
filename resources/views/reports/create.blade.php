<x-auth-layout :title="'Report a Concern - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 9v4M12 16.5h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    <path d="M10.3 3.9L2.9 17a1.5 1.5 0 001.3 2.2h15.6a1.5 1.5 0 001.3-2.2L13.7 3.9a1.5 1.5 0 00-2.6 0z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">Report a Concern</h2>
        <p class="mt-1 text-center text-sm text-gray-600">
            Tell us about a misconduct concern involving a school on ScholarNest. You may report anonymously.
        </p>

        @if ($errors->any())
            <div class="mt-4 rounded-[5px] bg-red-50 p-4 text-sm text-red-700 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data" class="mt-6 space-y-2">
            @csrf
            <x-honeypot />

            <x-select-field
                id="school_id"
                name="school_id"
                label="School (optional)"
                icon="M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11"
                helper="Leave blank if you're not sure which school this concerns."
                :options="collect(['' => 'Not sure / not applicable'])->union($schools->pluck('name', 'id'))"
                :selected="old('school_id')"
            />

            <x-text-field
                id="reporter_name"
                name="reporter_name"
                label="Your Name (optional)"
                icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                helper="Leave blank to submit this report anonymously."
                :value="old('reporter_name')"
                placeholder="Leave blank to stay anonymous"
            />

            <x-text-field
                id="reporter_email"
                name="reporter_email"
                label="Your Email (optional)"
                type="email"
                icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                helper="Only used if we need to follow up on your report."
                :value="old('reporter_email')"
                placeholder="So we can follow up, if needed"
            />

            <x-textarea-field
                id="description"
                name="description"
                label="What happened?"
                icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                helper="Share as much detail as you can: what happened, when, and who was involved."
                rows="5"
                required
                placeholder="Describe the concern in as much detail as you can..."
                :value="old('description')"
            />

            <div>
                <x-input-label for="media" value="Photo or Video Evidence (optional)" />
                <input
                    id="media"
                    name="media"
                    type="file"
                    accept=".jpg,.jpeg,.png,.mp4,.mov"
                    class="mt-1 w-full"
                >
                <p class="mt-1 text-xs text-gray-500">JPG, PNG, MP4, or MOV. Max 10MB.</p>
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Submit Report
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>
    </x-auth-card>
</x-auth-layout>
