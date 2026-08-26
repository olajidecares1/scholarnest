{{-- "Share via WhatsApp", offered at the one moment it can be.

     The password exists in readable form for exactly one page view - it was
     typed, hashed, and forgotten - so this banner appears on the response to
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
                class="flex h-[42px] shrink-0 items-center gap-2 rounded-[10px] bg-[#25D366] px-5 text-[13px] font-bold text-white shadow-[0_10px_24px_-12px_rgba(37,211,102,0.9)] transition hover:bg-[#1FB855]"
            >
                {{-- Inline, not an icon font: a brand glyph that fails to
                     load leaves a blank box where the button should be, and
                     the button then looks broken even though it works. --}}
                <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.465 3.488"/>
                </svg>
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
