{{-- "Share via WhatsApp", offered at the one moment it can be.

     The password exists in readable form for exactly one page view, it was
     typed, hashed, and forgotten, so this banner appears on the response to
     saving credentials and never again. There is no way to bring it back
     later, because there is nothing left to bring back: if the password is
     lost, a new one is set.

     The ID and the password are shown here too, so a School Admin who is
     handing them over in person, or writing them on a slip, does not have to
     use WhatsApp to see them. --}}
@php($share = session('credential_share'))

@if ($share)
    <div class="rounded-[5px] border border-green-200 bg-green-50 p-5 shadow-sm dark:border-green-900/50 dark:bg-green-900/20 lg:rounded-[10px]">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-sm font-bold text-green-900 dark:text-green-300">
                    <i class="fa-solid fa-circle-check"></i>
                    Login details created for {{ $share['person'] }}
                </h2>
                <p class="mt-1 text-[12px] leading-[1.6] text-green-800 dark:text-green-400">
                    Send them now &mdash; the password cannot be shown again once you leave this page.
                </p>

                <dl class="mt-3 inline-flex flex-wrap gap-x-6 gap-y-1 rounded-[8px] border border-green-200 bg-white px-4 py-2.5 dark:border-green-900/50 dark:bg-gray-800">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $share['id_label'] }}</dt>
                        <dd class="font-mono text-[12.5px] font-semibold text-gray-900 dark:text-white">{{ $share['identifier'] }}</dd>
                    </div>
                    <div x-data="{ shown: false }">
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Password</dt>
                        <dd class="flex items-center gap-2">
                            <span
                                class="font-mono text-[12.5px] font-semibold text-gray-900 dark:text-white"
                                x-text="shown ? @js($share['password'] ?? '') : '••••••••'"
                            >••••••••</span>
                            <button
                                type="button"
                                @click="shown = ! shown"
                                class="text-[11px] font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                x-text="shown ? 'Hide' : 'Show'"
                            >Show</button>
                        </dd>
                    </div>
                </dl>
            </div>

            <a
                href="{{ $share['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn flex h-[42px] shrink-0 items-center gap-2 rounded-[10px] bg-[#25D366] px-5 text-[13px] font-bold text-white shadow-[0_10px_24px_-12px_rgba(37,211,102,0.9)] transition hover:bg-[#1FB855]"
            >
                {{-- Inline, not an icon font: a brand glyph that fails to
                     load leaves a blank box where the button should be, and
                     the button then looks broken even though it works. --}}
                <i class="fa-brands fa-whatsapp text-[14px] leading-none" aria-hidden="true"></i>
                Share via WhatsApp
            </a>
        </div>

        @unless ($share['phone'])
            {{-- No number on file, so wa.me opens the contact picker instead of
                 a specific chat. Better than hiding the button: the School
                 Admin can still choose the right person. --}}
            <p class="mt-3 text-[11px] leading-[1.5] text-green-800 dark:text-green-400">
                No phone number is recorded for {{ $share['person'] }}, so WhatsApp will ask you who to send it to.
                Add their number to the record to open the chat directly next time.
            </p>
        @endunless
    </div>
@endif
