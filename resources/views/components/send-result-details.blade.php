@props([
    // Where the choice goes: the confirmation page for several students, or
    // straight to sending for one.
    'action',
    'label' => 'Send Result Details',
    'compact' => false,
    // An Alpine expression that disables the button (e.g. "selected.length === 0").
    'disabledWhen' => 'false',
    'target' => null,
    'icon' => 'fa-paper-plane',
])

{{-- Sends each parent/guardian their own child's result details.

     The main button sends by email AND WhatsApp at once, to every address
     on each child's record: one tap, every parent reached however they can
     be. The arrow beside it narrows that to email only or WhatsApp only.
     The one-row (compact) form opens the same three choices, "Email and
     WhatsApp" first.

     The students are whatever hidden fields the slot carries: ticked tokens,
     a batch, a class, or students of a result. School Admin routes only. --}}
<form method="POST" action="{{ $action }}" @if ($target) target="{{ $target }}" @endif x-data="{ open: false }" class="relative inline-block" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    @csrf
    {{ $slot }}

    @if ($compact)
        <button type="button" x-on:click="open = ! open" :disabled="{{ $disabledWhen }}" class="icon-btn icon-btn--neutral disabled:cursor-not-allowed disabled:opacity-50" data-tooltip="{{ $label }}" aria-label="{{ $label }}" :aria-expanded="open">
            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        </button>
    @else
        <span class="inline-flex overflow-hidden rounded-[8px] shadow-sm">
            <button type="submit" name="channel" value="both" :disabled="{{ $disabledWhen }}" class="btn inline-flex items-center gap-2 rounded-none bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50" data-tooltip="By email and WhatsApp, to every parent on each child's record">
                <i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i> {{ $label }}
            </button>
            <button type="button" x-on:click="open = ! open" :disabled="{{ $disabledWhen }}" class="btn inline-flex items-center rounded-none border-l border-green-500 bg-green-600 px-2.5 py-2 text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Email only or WhatsApp only" :aria-expanded="open">
                <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
            </button>
        </span>
    @endif

    {{-- Left-aligned on a phone, where a menu hung from the right edge of a
         button near the left of the screen ran off it. --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="absolute left-0 z-30 mt-1 w-56 overflow-hidden rounded-[8px] border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-800 sm:left-auto sm:right-0"
        role="menu"
    >
        <button type="submit" name="channel" value="both" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="fa-solid fa-share-nodes w-4 text-purple-600" aria-hidden="true"></i> Email and WhatsApp
        </button>
        <button type="submit" name="channel" value="email" role="menuitem" class="flex w-full items-center gap-2.5 border-t border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="fa-solid fa-envelope w-4 text-blue-600" aria-hidden="true"></i> Email only
        </button>
        <button type="submit" name="channel" value="whatsapp" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="fa-brands fa-whatsapp w-4 text-green-600" aria-hidden="true"></i> WhatsApp only
        </button>
    </div>
</form>
