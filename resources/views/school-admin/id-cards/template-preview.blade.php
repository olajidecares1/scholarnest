<x-dashboard-layout page-title="ID Card Template" page-subtitle="Exactly how your ID cards will look when you generate them.">
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('id-cards.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
                <i class="fa-solid fa-chevron-left text-[11px]"></i>
                Back to ID Cards
            </a>

            <a href="{{ route('id-cards.templates.index') }}" class="inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                Edit templates
            </a>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm font-bold text-gray-900 dark:text-white">This is the real template</p>
            <p class="field-hint mt-1">
                Rendered by the same template that produces your printed ID cards. The holder&rsquo;s details are
                sample data; your school&rsquo;s name, logo, watermark and colours are your own.
                @if ($template)
                    Showing your default template, &ldquo;{{ $template->name }}&rdquo;.
                @else
                    You have not saved a template for this card type yet, so this shows the AkademicNest default.
                @endif
            </p>

            <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[200px]">
                    <label class="field-label" for="holder">Card for</label>
                    <select id="holder" name="holder" class="mt-1.5 w-full" onchange="this.form.submit()">
                        @foreach (\App\Enums\IdCardHolderType::cases() as $case)
                            <option value="{{ $case->value }}" @selected($case === $holderType)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <noscript>
                    <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white">Show</button>
                </noscript>
            </form>

            @unless ($school->logoUrl())
                <p class="mt-3 rounded-[8px] bg-amber-50 px-3 py-2 text-[11.5px] text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                    Upload your school logo under Settings to see it on the card and as the watermark behind it.
                </p>
            @endunless
        </div>

        {{-- Front and back at twice actual size, which is the smallest at
             which the 5.5px detail text can be read on screen. --}}
        <div class="overflow-x-auto rounded-[10px] bg-gray-100 p-6 dark:bg-gray-900">
            <div class="flex flex-wrap justify-center gap-10">
                @foreach ([['Front', 'school-admin.id-cards._card'], ['Back', 'school-admin.id-cards._card_back']] as [$face, $partial])
                    <div class="text-center">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $face }}</p>
                        <div style="width: 409px; height: 648px;">
                            <div style="transform: scale(2); transform-origin: top left;">
                                @include($partial, ['card' => $card])
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-dashboard-layout>
