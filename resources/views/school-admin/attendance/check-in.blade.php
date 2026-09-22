<x-dashboard-layout page-title="QR Check-in" page-subtitle="One poster at the gate. Staff and students scan it on the way in and on the way out.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @include('school-admin.attendance._tabs', ['current' => 'check-in'])

        <form method="POST" action="{{ route('attendance.check-in.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Switch it on</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    While this is off, the poster's address leads nowhere and nothing changes about how your register is taken.
                </p>

                <label class="mt-4 flex items-start gap-3">
                    <input type="checkbox" name="check_in_enabled" value="1" class="mt-1" @checked(old('check_in_enabled', $school->check_in_enabled))>
                    <span class="text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">Allow check-in by QR code at this school</span><br>
                        <span class="text-gray-500 dark:text-gray-400">Staff can check themselves in as soon as this is on. Whether pupils can is the separate choice below.</span>
                    </span>
                </label>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">How the pupils' register is taken</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Your choice, and you can change it back at any time. Either way a teacher or the office can still correct any row by hand.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($modes as $mode)
                        <label class="flex cursor-pointer gap-3 rounded-[8px] border border-gray-200 p-4 transition-colors duration-150 hover:border-blue-400 dark:border-gray-700">
                            <input
                                type="radio"
                                name="student_attendance_mode"
                                value="{{ $mode->value }}"
                                class="mt-1"
                                @checked(old('student_attendance_mode', $school->student_attendance_mode?->value) === $mode->value)
                            >
                            <span class="text-sm">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $mode->label() }}</span><br>
                                <span class="text-gray-500 dark:text-gray-400">{{ $mode->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Where the school is</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    A printed code can be photographed, so a scan only counts from inside this circle. Find your school on a map,
                    right-click the gate, and copy the two numbers it gives you.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="latitude" value="Latitude" />
                        <input id="latitude" name="latitude" type="text" inputmode="decimal" class="mt-1 w-full" value="{{ old('latitude', $school->latitude) }}" placeholder="6.5244">
                        <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="longitude" value="Longitude" />
                        <input id="longitude" name="longitude" type="text" inputmode="decimal" class="mt-1 w-full" value="{{ old('longitude', $school->longitude) }}" placeholder="3.3792">
                        <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="check_in_radius_metres" value="Counted within (metres)" />
                        <input id="check_in_radius_metres" name="check_in_radius_metres" type="number" min="25" max="2000" class="mt-1 w-full" value="{{ old('check_in_radius_metres', $school->check_in_radius_metres) }}">
                        <x-input-error :messages="$errors->get('check_in_radius_metres')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">The poster</h2>

            @if ($posterUrl)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    A3, with your school's name and logo above the code. Print it, laminate it, and put it where the queue forms.
                </p>
                <p class="mt-3 break-all rounded-[6px] bg-gray-50 p-3 font-mono text-xs text-gray-600 dark:bg-gray-900 dark:text-gray-300">{{ $posterUrl }}</p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('attendance.check-in.poster') }}" class="rounded-[6px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors duration-150 hover:bg-blue-700">Download the poster</a>

                    <form method="POST" action="{{ route('attendance.check-in.rotate') }}" onsubmit="return confirm('The poster on the wall will stop working immediately. Print and put up the new one. Continue?')">
                        @csrf
                        <button type="submit" class="rounded-[6px] border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 transition-colors duration-150 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-900/20">Issue a new code</button>
                    </form>
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Issue a new code if the poster has been photographed and passed around. The only cost is a reprint.
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Switch check-in on and save, and the poster appears here.</p>
            @endif
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">The last 25 scans</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Refusals included. A scan from far away, or one held on a phone until it found signal, shows here with the time it was really made.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Who</th>
                            <th class="px-6 py-3">When</th>
                            <th class="px-6 py-3">In or out</th>
                            <th class="px-6 py-3">Distance</th>
                            <th class="px-6 py-3">Outcome</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($recentScans as $scan)
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ $scan->personName() }}
                                    <span class="ml-1 text-xs text-gray-400">{{ $scan->student_id ? 'Student' : 'Staff' }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $scan->scanned_at->setTimezone($school->timezone ?: config('app.timezone'))->format('D j M, g:i:sa') }}
                                    @if ($scan->was_queued)
                                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">sent later</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $scan->kind->label() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $scan->distance_metres === null ? '—' : $scan->distance_metres.'m' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $scan->outcome->badgeClasses() }}">{{ $scan->outcome->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">Nobody has scanned yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
