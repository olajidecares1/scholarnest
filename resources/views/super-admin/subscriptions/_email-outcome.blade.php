{{-- What happened to the emails after an approval or a resend. A failure is
     shown in red beside the green "approved" message, never folded into it. --}}
@if (session('email_error'))
    <div class="flex items-start gap-3 rounded-[5px] border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300 lg:rounded-[10px]" role="alert">
        <i class="fa-solid fa-envelope-circle-exclamation mt-0.5 text-base" aria-hidden="true"></i>
        <div>
            <p class="font-bold">Email not delivered</p>
            <p class="mt-0.5 break-words">{{ session('email_error') }}</p>
        </div>
    </div>
@endif
