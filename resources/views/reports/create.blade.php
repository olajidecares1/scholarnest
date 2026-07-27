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
            Tell us about a misconduct concern involving a school on EduNest. You may report anonymously.
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

        <form method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
            @csrf

            <div>
                <x-input-label for="school_id" value="School (optional)" />
                <select
                    id="school_id"
                    name="school_id"
                    class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 lg:rounded-[10px]"
                >
                    <option value="">Not sure / not applicable</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="reporter_name" value="Your Name (optional)" />
                <x-text-input id="reporter_name" name="reporter_name" type="text" class="mt-1" :value="old('reporter_name')" placeholder="Leave blank to stay anonymous" />
            </div>

            <div>
                <x-input-label for="reporter_email" value="Your Email (optional)" />
                <x-text-input id="reporter_email" name="reporter_email" type="email" class="mt-1" :value="old('reporter_email')" placeholder="So we can follow up, if needed" />
            </div>

            <div>
                <x-input-label for="description" value="What happened?" />
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    required
                    placeholder="Describe the concern in as much detail as you can..."
                    class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 placeholder:text-gray-400 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 lg:rounded-[10px]"
                >{{ old('description') }}</textarea>
            </div>

            <div>
                <x-input-label for="media" value="Photo or Video Evidence (optional)" />
                <input
                    id="media"
                    name="media"
                    type="file"
                    accept=".jpg,.jpeg,.png,.mp4,.mov"
                    class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-2.5 pl-3 pr-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[5px] file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-700 lg:rounded-[10px]"
                >
                <p class="mt-1 text-xs text-gray-500">JPG, PNG, MP4, or MOV. Max 10MB.</p>
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 lg:rounded-[10px]"
            >
                Submit Report
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>
    </x-auth-card>
</x-auth-layout>
