@if (session('status'))
    <div class="flex items-start gap-2 rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]" role="status">
        <i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i>
        <span>{{ session('status') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="flex items-start gap-2 rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif
