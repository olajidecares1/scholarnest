@php
    $cardClass = 'rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]';
    $whatsApp = $summary['whatsapp'];
    $otherRoles = array_sum($summary['other_roles']);
    $failedCount = count($summary['failed']);
    $hasShares = $shares !== [];
    $statusChip = [
        'sent' => ['Sent', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'fa-check'],
        'failed' => ['Failed', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'fa-xmark'],
        'not_configured' => ['Not sent', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'fa-clock'],
        'no_phone' => ['No phone', 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300', 'fa-phone-slash'],
    ];
@endphp

<x-dashboard-layout page-title="Staff Registration Summary" page-subtitle="What happened to every row of {{ $summary['file_name'] }}.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Go to Staff
            </a>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('teacher-assignments.index') }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><i class="fa-solid fa-chalkboard-user text-[12px]" aria-hidden="true"></i> Teacher Assignments</a>
                <a href="{{ route('staff.import.create') }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-blue-600 px-3 py-2 text-xs font-semibold text-blue-600"><i class="fa-solid fa-upload text-[12px]" aria-hidden="true"></i> Upload Another List</a>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                ['Total uploaded', $summary['total'], 'text-gray-900 dark:text-white', 'fa-file-lines text-gray-400'],
                ['Registered', $summary['registered'], 'text-green-700 dark:text-green-400', 'fa-user-check text-green-500'],
                ['Class teachers', $summary['class_teachers'], 'text-blue-700 dark:text-blue-400', 'fa-chalkboard-user text-blue-500'],
                ['Other roles', $otherRoles, 'text-indigo-700 dark:text-indigo-400', 'fa-users text-indigo-500'],
                ['Not registered', $failedCount, $failedCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500', 'fa-user-xmark '.($failedCount ? 'text-red-500' : 'text-gray-400')],
                ['WhatsApp sent', $whatsApp['sent'].' / '.$summary['registered'], 'text-emerald-700 dark:text-emerald-400', 'fa-brands fa-whatsapp text-emerald-500'],
            ] as [$label, $value, $colour, $icon])
                <div class="{{ $cardClass }} p-4">
                    <div class="flex items-center justify-between">
                        <small class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</small>
                        <i class="{{ str_contains($icon, 'fa-brands') ? '' : 'fa-solid' }} {{ $icon }} text-[13px]" aria-hidden="true"></i>
                    </div>
                    <p class="mt-1 text-2xl font-extrabold {{ $colour }}">{{ is_int($value) ? number_format($value) : $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($summary['other_roles'] !== [])
            <div class="{{ $cardClass }} px-5 py-3">
                <small class="text-xs text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">Other roles:</span>
                    {{ collect($summary['other_roles'])->map(fn ($count, $role) => "{$role} {$count}")->implode(' · ') }}
                </small>
            </div>
        @endif

        {{-- WhatsApp --}}
        @if (! $whatsApp['configured'])
            <div class="flex gap-2 rounded-[8px] bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                <i class="fa-brands fa-whatsapp mt-0.5 text-[15px]" aria-hidden="true"></i>
                <small class="block text-xs leading-relaxed">
                    Automatic WhatsApp sending is not set up yet, so no messages were sent.
                    @if ($hasShares)
                        Use the <span class="font-semibold">Send on WhatsApp</span> button beside each person below; it opens WhatsApp with their login details written out.
                    @endif
                </small>
            </div>
        @elseif ($whatsApp['failed'] > 0)
            <div class="flex gap-2 rounded-[8px] bg-red-50 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-300" role="alert">
                <i class="fa-brands fa-whatsapp mt-0.5 text-[15px]" aria-hidden="true"></i>
                <small class="block text-xs leading-relaxed">{{ number_format($whatsApp['failed']) }} WhatsApp {{ Str::plural('message', $whatsApp['failed']) }} could not be sent. The reason is beside each person{{ $hasShares ? ', with a button to send it yourself' : '' }}.</small>
            </div>
        @endif

        @if ($hasShares)
            <div class="flex gap-2 rounded-[8px] bg-blue-50 p-4 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                <i class="fa-solid fa-key mt-0.5 text-[13px]" aria-hidden="true"></i>
                <small class="block text-xs leading-relaxed">
                    Passwords are shown on this page <span class="font-semibold">once only</span>. They are not stored anywhere they can be read back, so share any that were not sent before you leave or reload this page. A forgotten password can be reset from the staff member's page.
                </small>
            </div>
        @endif

        @if ($summary['created'] !== [])
            <div class="{{ $cardClass }}">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">Registered ({{ number_format($summary['registered']) }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Staff ID</th>
                                <th class="px-4 py-3 font-semibold">Role</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Class / Subjects</th>
                                @if ($hasShares)<th class="px-4 py-3 font-semibold">Password</th>@endif
                                <th class="px-4 py-3 font-semibold">WhatsApp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($summary['created'] as $person)
                                @php
                                    $share = $shares[$person['uuid']] ?? null;
                                    [$chipLabel, $chipClass, $chipIcon] = $statusChip[$person['whatsapp']] ?? $statusChip['not_configured'];
                                @endphp
                                <tr class="align-top">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $person['name'] }}</span>
                                        @if ($person['phone'])<small class="block text-xs text-gray-500 dark:text-gray-400">{{ $person['phone'] }}</small>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $person['staff_number'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $person['role_label'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        {{ implode(', ', $person['classes']) }}
                                        @if ($person['subjects'])<small class="block text-xs text-gray-500 dark:text-gray-400">{{ implode(', ', $person['subjects']) }}</small>@endif
                                        @foreach ($person['notes'] ?? [] as $note)<small class="block text-xs text-amber-700 dark:text-amber-400">{{ $note }}</small>@endforeach
                                    </td>
                                    @if ($hasShares)
                                        <td class="whitespace-nowrap px-4 py-3">
                                            @if ($share)
                                                <span class="select-all font-mono text-xs text-gray-800 dark:text-gray-100">{{ $share['password'] }}</span>
                                                <small class="block text-[11px] text-gray-400">{{ $person['password_source'] === 'file' ? 'from the file' : 'generated' }}</small>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="min-w-[11rem] px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $chipClass }}"><i class="fa-solid {{ $chipIcon }} text-[10px]" aria-hidden="true"></i> {{ $chipLabel }}</span>
                                        @if ($person['whatsapp'] !== 'sent' && $person['whatsapp_error'] && $person['whatsapp'] !== 'not_configured')
                                            <small class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $person['whatsapp_error'] }}</small>
                                        @endif
                                        @if ($share && $person['whatsapp'] !== 'sent')
                                            <a href="{{ $share['url'] }}" target="_blank" rel="noopener" class="mt-1.5 inline-flex items-center gap-1.5 rounded-[6px] bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700">
                                                <i class="fa-brands fa-whatsapp text-[12px]" aria-hidden="true"></i> Send on WhatsApp
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($summary['failed'] !== [])
            <div class="{{ $cardClass }}">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">Not registered ({{ number_format($failedCount) }})</h3>
                    <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Correct these in your file and upload just these rows again, or add them with Add Staff.</small>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($summary['failed'] as $failure)
                        <li class="flex gap-4 px-5 py-3">
                            <span class="w-14 shrink-0 font-mono text-xs text-gray-500">Row {{ $failure['line'] }}</span>
                            <div class="min-w-0">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $failure['name'] }}</span>
                                @foreach ($failure['reasons'] as $reason)
                                    <small class="block text-xs leading-relaxed text-red-700 dark:text-red-400">{{ $reason }}</small>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-dashboard-layout>
