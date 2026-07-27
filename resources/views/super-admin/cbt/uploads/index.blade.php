@php
    $statusStyles = [
        'pending' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        'processing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'needs_mapping' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    ];
@endphp

<x-super-admin-layout page-title="Import CBT Questions" page-subtitle="Upload a PDF or Word document and let AI extract the questions automatically.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('super-admin.cbt.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to CBT
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Upload a Document</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                PDF or Word (.doc/.docx), up to 20MB. If the document covers multiple examination years, every year is detected and split automatically.
                Leave exam body/subject blank to let AI detect them from the document.
            </p>

            <form method="POST" action="{{ route('super-admin.cbt.uploads.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf

                <div>
                    <x-input-label for="cbt-upload-file" value="Document" />
                    <input
                        id="cbt-upload-file"
                        name="file"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        required
                        class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[5px] file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 lg:rounded-[10px]"
                    >
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cbt-upload-exam-body" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Exam Body (optional)</label>
                        <select
                            id="cbt-upload-exam-body"
                            name="cbt_exam_body_id"
                            class="w-full rounded-[8px] border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 shadow-sm transition-colors duration-200 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">Let AI detect it</option>
                            @foreach ($examBodies as $examBody)
                                <option value="{{ $examBody->id }}">{{ $examBody->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="cbt-upload-subject" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Subject (optional)</label>
                        <select
                            id="cbt-upload-subject"
                            name="cbt_subject_id"
                            class="w-full rounded-[8px] border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 shadow-sm transition-colors duration-200 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">Let AI detect it</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="submit" class="flex items-center gap-2 rounded-[5px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md lg:rounded-[10px]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4v12M7 9l5-5 5 5M5 20h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Upload & Extract
                </button>
            </form>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Uploads</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Every document ever uploaded, and what came out of it.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">File</th>
                            <th class="px-6 py-3 font-semibold">Exam Body / Subject</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Questions</th>
                            <th class="px-6 py-3 font-semibold">Uploaded</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($uploads as $upload)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $upload->original_filename }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $upload->examBody?->code ?? '—' }} @if ($upload->subject) &middot; {{ $upload->subject->name }} @endif
                                </td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusStyles[$upload->status->value] }}">{{ $upload->status->label() }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $upload->questions_extracted_count }}
                                    @if ($upload->questions_needing_review_count > 0)
                                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ $upload->questions_needing_review_count }} need review</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $upload->created_at->diffForHumans() }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('super-admin.cbt.uploads.show', $upload) }}" class="rounded-[5px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 lg:rounded-[8px]">Review</a>
                                        <form method="POST" action="{{ route('super-admin.cbt.uploads.destroy', $upload) }}" onsubmit="return confirm('Delete this upload? Extracted questions remain in the question bank.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[5px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 lg:rounded-[8px]">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No documents uploaded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($uploads->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $uploads->links() }}
                </div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
