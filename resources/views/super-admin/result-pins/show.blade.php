@php
    use App\Enums\ResultCheckingPinStatus;
@endphp

<x-super-admin-layout :page-title="'Result PINs — '.$school->name" page-subtitle="Generate and monitor this school's result-checking PIN pool.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($generatedCodes)
            <div class="rounded-[5px] border-2 border-blue-200 bg-blue-50 p-5 dark:border-blue-800 dark:bg-blue-900/20 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-blue-900 dark:text-blue-300">Newly Generated PINs — send these to {{ $school->name }}</h3>
                <p class="mt-0.5 text-xs text-blue-700 dark:text-blue-400">These codes won't be shown again in full. The school must assign each one to an examination before it can be used.</p>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($generatedCodes as $code)
                        <code class="rounded-[8px] border border-blue-200 bg-white px-3 py-2 text-center text-sm font-bold tracking-wide text-blue-900 dark:border-blue-800 dark:bg-gray-800 dark:text-blue-300">{{ $code }}</code>
                    @endforeach
                </div>
            </div>
        @endif

        <a href="{{ route('super-admin.result-pins.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Schools
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $school->name }}'s Result-Checking PINs</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">The school assigns each PIN to one of its own examinations before handing it out.</p>
                </div>
                <div class="flex items-center gap-2">
                    <form method="GET" class="flex items-center gap-2">
                        <select name="status" onchange="this.form.submit()" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                    <button
                        type="button"
                        @click="open = true"
                        class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                        Generate PINs
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Code</th>
                            <th class="px-5 py-3 font-semibold">Examination</th>
                            <th class="px-5 py-3 font-semibold">Bound Student</th>
                            <th class="px-5 py-3 font-semibold">Uses</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($pins as $pin)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-mono font-semibold text-gray-900 dark:text-white">{{ $pin->code }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($pin->examination)
                                        {{ $pin->examination->name }} &middot; {{ $pin->examination->class_name }}
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $pin->boundStudent?->fullName() ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $pin->uses_count }} / {{ $pin->max_uses }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $pin->status === ResultCheckingPinStatus::Active,
                                        'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $pin->status === ResultCheckingPinStatus::Exhausted,
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $pin->status === ResultCheckingPinStatus::Revoked,
                                    ])>{{ $pin->status->label() }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($pin->status === ResultCheckingPinStatus::Active)
                                        <form method="POST" action="{{ route('super-admin.result-pins.revoke', $pin) }}" onsubmit="return confirm('Revoke PIN {{ $pin->code }}? It will stop working immediately.');">
                                            @csrf
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No result-checking PINs generated for this school yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pins->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $pins->links() }}
                </div>
            @endif
        </div>

        {{-- Generate PINs modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Generate Result-Checking PINs</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">For {{ $school->name }}. Generate any quantity — from a handful to thousands at once.</p>
                <form method="POST" action="{{ route('super-admin.result-pins.generate', $school) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-text-field name="quantity" label="Number of PINs to Generate" type="number" min="1" max="5000" value="10" required />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Generate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-super-admin-layout>
