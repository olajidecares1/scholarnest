<x-student-layout page-title="Co-curricular" page-subtitle="Join clubs and activities">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($activities as $activity)
            @php $joined = $joinedIds->contains($activity->id); @endphp
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $activity->name }}</p>
                    @if ($activity->category)
                        <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $activity->category }}</span>
                    @endif
                </div>
                @if ($activity->description)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $activity->description }}</p>
                @endif
                @if ($activity->schedule_text)
                    <p class="mt-2 text-xs font-medium text-gray-600 dark:text-gray-300">{{ $activity->schedule_text }}</p>
                @endif
                <p class="mt-2 text-xs text-gray-400">{{ $activity->students_count }} student(s) joined</p>

                <form method="POST" action="{{ $joined ? route('student.co-curricular.leave', [$school, $activity]) : route('student.co-curricular.join', [$school, $activity]) }}" class="mt-4">
                    @csrf
                    @if ($joined) @method('DELETE') @endif
                    <button
                        type="submit"
                        class="w-full rounded-[8px] px-4 py-2 text-sm font-semibold transition-all duration-300 ease-out hover:-translate-y-0.5
                            {{ $joined
                                ? 'border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20'
                                : 'bg-primary-600 text-white hover:bg-primary-700 hover:shadow-md' }}"
                    >
                        {{ $joined ? 'Leave' : 'Join' }}
                    </button>
                </form>
            </div>
        @empty
            <div class="col-span-full rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">No activities available yet.</p>
            </div>
        @endforelse
    </div>
</x-student-layout>
