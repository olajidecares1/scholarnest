<div class="space-y-3">
    @foreach ([
        ['label' => 'Continue with Google', 'icon' => 'google'],
        ['label' => 'Continue with Microsoft', 'icon' => 'microsoft'],
        ['label' => 'Continue with Apple', 'icon' => 'apple'],
    ] as $provider)
        <button
            type="button"
            disabled
            title="Coming soon"
            class="flex w-full cursor-not-allowed items-center justify-center gap-3 rounded-[5px] border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm lg:rounded-[10px]"
        >
            @if ($provider['icon'] === 'google')
                <svg class="h-4 w-4" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.616z" />
                    <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" />
                    <path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z" />
                    <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.167 6.656 3.58 9 3.58z" />
                </svg>
            @elseif ($provider['icon'] === 'microsoft')
                <svg class="h-4 w-4" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="1" width="9" height="9" fill="#F25022" />
                    <rect x="11" y="1" width="9" height="9" fill="#7FBA00" />
                    <rect x="1" y="11" width="9" height="9" fill="#00A4EF" />
                    <rect x="11" y="11" width="9" height="9" fill="#FFB900" />
                </svg>
            @else
                <svg class="h-4 w-4 text-gray-900" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.256-1.766-2.264-4.502-2.264-7.084 0-4.174 2.7-6.395 5.353-6.395 1.36 0 2.49.9 3.34.9.81 0 2.08-.96 3.61-.96.6 0 2.79.05 4.22 2.11-.11.07-2.52 1.47-2.52 4.5 0 3.57 3.12 4.88 3.16 4.9z" />
                </svg>
            @endif
            {{ $provider['label'] }}
            <span class="ml-auto rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-500">Soon</span>
        </button>
    @endforeach
</div>
