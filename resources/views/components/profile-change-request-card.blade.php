@props(['action', 'fields', 'requests'])

<div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Request a Change</h2>
    <small class="field-hint mt-1">These details can only be changed by the school office. Submit a request and it will be reviewed.</small>

    <form method="POST" action="{{ $action }}" class="mt-4 space-y-2">
        @csrf

        <div>
            <label class="field-label mb-1">Field</label>
            <select name="field_key" required class="w-full">
                @foreach ($fields as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <x-text-field name="requested_value" label="Requested Value" required />
        <x-textarea-field name="reason" label="Reason (optional)" rows="2" />

        <div class="flex justify-end">
            <button type="submit" class="btn rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700">Submit Request</button>
        </div>
    </form>

    @if ($requests->isNotEmpty())
        <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-700">
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Your Requests</h3>
            <ul class="mt-3 space-y-2">
                @foreach ($requests as $changeRequest)
                    <li class="flex items-center justify-between gap-3 rounded-[8px] bg-gray-50 px-3 py-2 text-xs dark:bg-gray-900/40">
                        <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300">
                            <span class="font-semibold">{{ $changeRequest->field_label }}</span> &rarr; {{ $changeRequest->requested_value }}
                        </span>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 font-semibold uppercase',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $changeRequest->status === 'pending',
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $changeRequest->status === 'approved',
                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $changeRequest->status === 'rejected',
                        ])>{{ $changeRequest->status }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
