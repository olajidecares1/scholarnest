{{-- Status, notes and errors shared by the Update Class pages. --}}
@if (session('status'))
    <div class="rounded-[5px] bg-green-50 p-4 font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]" role="status">
        <small class="block text-xs leading-relaxed">{{ session('status') }}</small>
    </div>
@endif

@if (session('roster_notes'))
    <div class="rounded-[5px] bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 lg:rounded-[10px]">
        <p class="text-xs font-semibold">Please note</p>
        <ul class="mt-1 list-inside list-disc space-y-1">
            @foreach (session('roster_notes') as $note)
                <li><small class="text-xs leading-relaxed">{{ $note }}</small></li>
            @endforeach
        </ul>
    </div>
@endif

@if ($errors->any())
    <div class="rounded-[5px] bg-red-50 p-4 text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]" role="alert">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li><small class="text-xs leading-relaxed">{{ $error }}</small></li>
            @endforeach
        </ul>
    </div>
@endif
