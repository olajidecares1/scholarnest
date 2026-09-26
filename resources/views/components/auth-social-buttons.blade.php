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
            class="btn flex w-full cursor-not-allowed items-center justify-center gap-3 rounded-[8px] border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm"
        >
            @if ($provider['icon'] === 'google')
                <i class="fa-brands fa-google text-[#4285F4] text-[14px] leading-none" aria-hidden="true"></i>
            @elseif ($provider['icon'] === 'microsoft')
                <i class="fa-brands fa-microsoft text-[#00A4EF] text-[14px] leading-none" aria-hidden="true"></i>
            @else
                <i class="fa-brands fa-apple text-gray-900 text-[14px] leading-none" aria-hidden="true"></i>
            @endif
            {{ $provider['label'] }}
            <span class="ml-auto rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-500">Soon</span>
        </button>
    @endforeach
</div>
