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
@php($formId = 'send-details-'.\Illuminate\Support\Str::random(8))
{{-- The menu floats over the page (teleported to <body>, fixed), placed
     against the button and kept inside the screen: inside a card or a
     scrolling table it would otherwise widen what scrolls, sliding the card
     sideways, or be cut off by it. Opens upwards when there is no room below
     (the phone's bottom bar). Its buttons reach the form through form="". --}}
<form method="POST" action="{{ $action }}" id="{{ $formId }}" @if ($target) target="{{ $target }}" @endif
      x-data="{
          open: false,
          style: '',
          toggle(trigger) {
              this.open = ! this.open
              if (this.open) this.$nextTick(() => this.place(trigger))
          },
          place(trigger) {
              const menu = document.getElementById('{{ $formId }}-menu')
              if (! menu) return
              const r = trigger.getBoundingClientRect()
              const w = menu.offsetWidth, h = menu.offsetHeight
              const vw = window.innerWidth, vh = window.innerHeight, gap = 6, edge = 8
              // The phone's bottom navigation bar, when there is one.
              const nav = [...document.querySelectorAll('.edn-glass-nav')].map(n => n.getBoundingClientRect()).find(b => b.height > 0 && b.top > vh / 2)
              const bottom = nav ? Math.min(vh, nav.top) : vh
              let left = Math.min(Math.max(edge, r.right - w), vw - w - edge)
              let top = r.bottom + gap
              if (top + h > bottom - edge && r.top - gap - h >= edge) top = r.top - gap - h
              this.style = `left:${left}px;top:${Math.max(edge, top)}px`
          },
      }"
      class="relative inline-block"
      x-on:click.window="open && ! $el.contains($event.target) && ! document.getElementById('{{ $formId }}-menu')?.contains($event.target) && (open = false)"
      x-on:keydown.escape.window="open = false"
      x-on:scroll.window.passive="open = false"
      x-on:resize.window="open = false">
    @csrf
    {{ $slot }}

    @if ($compact)
        <button type="button" x-on:click="toggle($el)" :disabled="{{ $disabledWhen }}" class="icon-btn icon-btn--neutral disabled:cursor-not-allowed disabled:opacity-50" data-tooltip="{{ $label }}" :data-tooltip="open ? null : @js($label)" aria-label="{{ $label }}" :aria-expanded="open">
            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        </button>
    @else
        <span class="inline-flex overflow-hidden rounded-[8px] shadow-sm">
            <button type="submit" name="channel" value="both" :disabled="{{ $disabledWhen }}" class="btn inline-flex items-center gap-2 rounded-none bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50" data-tooltip="By email and WhatsApp, to every parent on each child's record">
                <i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i> {{ $label }}
            </button>
            <button type="button" x-on:click="toggle($el.parentElement)" :disabled="{{ $disabledWhen }}" class="btn inline-flex items-center rounded-none border-l border-green-500 bg-green-600 px-2.5 py-2 text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Email only or WhatsApp only" :aria-expanded="open">
                <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
            </button>
        </span>
    @endif

    <template x-teleport="body">
    <div
        id="{{ $formId }}-menu"
        x-show="open"
        x-cloak
        x-transition.opacity
        :style="style"
        class="fixed z-[80] w-56 overflow-hidden rounded-[8px] border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-800"
        role="menu"
    >
        <button type="submit" form="{{ $formId }}" name="channel" value="both" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="fa-solid fa-share-nodes w-4 text-purple-600" aria-hidden="true"></i> Email and WhatsApp
        </button>
        <button type="submit" form="{{ $formId }}" name="channel" value="email" role="menuitem" class="flex w-full items-center gap-2.5 border-t border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="fa-solid fa-envelope w-4 text-blue-600" aria-hidden="true"></i> Email only
        </button>
        <button type="submit" form="{{ $formId }}" name="channel" value="whatsapp" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="fa-brands fa-whatsapp w-4 text-green-600" aria-hidden="true"></i> WhatsApp only
        </button>
    </div>
    </template>
</form>
