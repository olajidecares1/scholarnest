<x-super-admin-layout page-title="Communications" page-subtitle="Send platform-wide announcements to school admins.">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if (session('status'))
                <div class="mb-6 rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Announcement History</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($announcements as $announcement)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $announcement->title }}</p>
                                <span class="text-xs text-gray-400">{{ $announcement->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $announcement->body }}</p>
                            <p class="mt-2 text-xs text-gray-400">Sent by {{ $announcement->sentBy->name }} to {{ $announcement->recipients_count }} school admin(s)</p>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No announcements sent yet.</p>
                    @endforelse
                </div>

                @if ($announcements->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                        {{ $announcements->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">New Announcement</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This will be sent to all {{ number_format($schoolAdminCount) }} school admin(s) by email and in-app notification.</p>

                <form method="POST" action="{{ route('super-admin.communications.store') }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="title" value="Title" />
                        <x-text-input id="title" name="title" type="text" class="mt-1" :value="old('title')" required autofocus placeholder="e.g. Scheduled Maintenance Notice" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="body" value="Message" />
                        <textarea
                            id="body"
                            name="body"
                            rows="5"
                            required
                            placeholder="Write your announcement..."
                            class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 placeholder:text-gray-400 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                        >{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg lg:rounded-[10px]"
                    >
                        Send Announcement
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-super-admin-layout>
