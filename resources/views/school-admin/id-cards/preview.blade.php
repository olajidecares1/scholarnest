<x-dashboard-layout page-title="ID Card Preview" :page-subtitle="$card->holder()->fullName()">
    <div class="space-y-6" x-data="{ side: 'front' }">
        <a href="{{ route('id-cards.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to ID Cards
        </a>

        @if (! $card->template)
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                No default template exists for {{ $card->holder_type->label() }} cards yet. <a href="{{ route('id-cards.templates.index') }}" class="font-semibold underline">Create one</a> to preview and print cards.
            </div>
        @else
            <div class="flex flex-col items-center gap-4 rounded-[10px] border border-gray-200 bg-gray-50 p-10 dark:border-gray-700 dark:bg-gray-900/40">
                <div class="inline-flex rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                    <button type="button" @click="side = 'front'" :class="side === 'front' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-xs font-semibold transition-colors duration-200">Front</button>
                    <button type="button" @click="side = 'back'" :class="side === 'back' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-xs font-semibold transition-colors duration-200">Back</button>
                </div>

                <div x-show="side === 'front'">
                    @include('school-admin.id-cards._card', ['card' => $card])
                </div>
                <div x-show="side === 'back'" style="display: none;">
                    @include('school-admin.id-cards._card_back', ['card' => $card])
                </div>

                <small class="block text-xs font-semibold text-gray-400">{{ $card->card_number }}</small>
            </div>

            <div class="flex flex-wrap justify-center gap-3">
                <form method="POST" action="{{ route('id-cards.print') }}" target="_blank">
                    @csrf
                    <input type="hidden" name="type" value="{{ $card->holder_type->value }}">
                    <input type="hidden" name="records[]" value="{{ $card->holder_uuid }}">
                    <input type="hidden" name="template" value="{{ $card->template->uuid }}">
                    <button type="submit" class="btn rounded-[8px] border border-gray-300 px-6 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Print</button>
                </form>
                <form method="POST" action="{{ route('id-cards.pdf') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $card->holder_type->value }}">
                    <input type="hidden" name="records[]" value="{{ $card->holder_uuid }}">
                    <input type="hidden" name="template" value="{{ $card->template->uuid }}">
                    <button type="submit" class="btn rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700">Download PDF</button>
                </form>
            </div>
        @endif
    </div>
</x-dashboard-layout>
