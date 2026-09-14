@php
    use App\Enums\IssuedIdCardStatus;
@endphp

<x-dashboard-layout page-title="Issued ID Cards" page-subtitle="Every card ever generated for this school, with its permanent number and QR verification link.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('id-cards.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to ID Cards
            </a>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <select name="type" onchange="this.form.submit()" >
                    <option value="">All Types</option>
                    @foreach ($holderTypes as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.submit()" >
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="overflow-x-auto rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Card Number</th>
                            <th class="px-5 py-3 font-semibold">Holder</th>
                            <th class="px-5 py-3 font-semibold">Type</th>
                            <th class="px-5 py-3 font-semibold">Issued</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($cards as $card)
                            @php $holder = $card->holder(); @endphp
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-mono font-semibold text-gray-900 dark:text-white">{{ $card->card_number }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $holder?->fullName() ?? 'N/A' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $card->holder_type->label() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $card->issued_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $card->status === IssuedIdCardStatus::Active,
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $card->status === IssuedIdCardStatus::Revoked,
                                    ])>{{ $card->status->label() }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($holder)
                                            <button type="button" @click="$store.idCardPreview.openPreview('{{ route('id-cards.preview', [$card->holder_type->value, $card->holder_uuid]) }}', '{{ route('id-cards.print') }}', '{{ route('id-cards.pdf') }}')" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Preview</button>
                                        @endif
                                        @if ($card->status === IssuedIdCardStatus::Active)
                                            <form method="POST" action="{{ route('id-cards.issued.revoke', $card) }}" onsubmit="return confirm('Revoke card {{ $card->card_number }}? Its QR code will show as invalid immediately.');">
                                                @csrf
                                                <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700">Revoke</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No ID cards have been generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($cards->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $cards->links() }}
                </div>
            @endif
        </div>
    </div>

    <x-id-card-preview-modal />
</x-dashboard-layout>
