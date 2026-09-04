<x-dashboard-layout page-title="Settings" page-subtitle="Manage your school's profile, branding, and preferences.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">School Profile</h2>
            <p class="field-hint mt-0.5">This information is used across your dashboard and public website.</p>

            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-4">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" class="h-16 w-16 rounded-[8px] border border-gray-200 object-cover dark:border-gray-700">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-xs font-semibold text-gray-400 dark:border-gray-600 dark:text-gray-500">No logo</span>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="w-full">
                        <p class="field-hint mt-1">School logo (optional). Leave blank to keep the existing one.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    @if ($school->faviconUrl())
                        <img src="{{ $school->faviconUrl() }}" class="h-16 w-16 rounded-[8px] border border-gray-200 object-contain p-2 dark:border-gray-700">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-xs font-semibold text-gray-400 dark:border-gray-600 dark:text-gray-500">No favicon</span>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp" class="w-full">
                        <p class="field-hint mt-1">Browser tab icon for your public website (optional, square PNG recommended). Your school's own icon is never replaced with ScholarNest's.</p>
                    </div>
                </div>

                {{-- The official stamp.

                     On every plan, deliberately: a stamp is how a school's own
                     paperwork is recognised, not a premium extra.

                     What is stored is NOT the photograph uploaded here. The
                     mark is lifted off its paper, trimmed to the ink and kept
                     on transparency, so it prints over a report card rather
                     than as a white square on top of one. See
                     App\Services\StampImage. --}}
                <div class="rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="text-[13px] font-extrabold text-gray-900 dark:text-white">Official School Stamp</h3>
                    <small class="field-hint mt-0.5 block">
                        Used on ID cards, report cards, certificates and other official documents. Available on every plan.
                    </small>

                    <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start">
                        {{-- Checked against the light AND dark ground, because
                             a stamp is stored on transparency and dark ink is
                             invisible against a dark panel. --}}
                        <span class="flex h-24 w-24 shrink-0 items-center justify-center rounded-[8px] border border-gray-200 bg-white p-2 dark:border-gray-700">
                            @if ($school->hasStamp())
                                <img src="{{ $school->stampDataUri() }}" alt="Your school stamp" class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-center text-[10px] font-semibold text-gray-400 dark:text-gray-500">No stamp</span>
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <label class="field-label" for="school_stamp">Upload your stamp</label>
                            <input id="school_stamp" type="file" name="stamp" accept=".png,.jpg,.jpeg,.webp" class="mt-1.5 w-full text-sm">
                            <small class="field-hint mt-1 block">
                                Photograph or scan your stamp pressed onto plain white paper, then upload it here.
                                The background is removed automatically and the stamp is trimmed for you &mdash;
                                you do not need to edit the image first. JPEG, PNG or WebP, up to 5MB.
                            </small>
                            @error('stamp')<p class="field-error">{{ $message }}</p>@enderror

                            @if ($school->hasStamp())
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="remove_stamp" value="1" class="rounded text-primary-500">
                                    Remove the current stamp
                                </label>
                                <small class="field-hint mt-1 block">Documents generated afterwards will carry no stamp.</small>
                            @endif
                        </div>
                    </div>
                </div>

                <x-text-field name="name" label="School Name" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" :value="old('name', $school->name)" required />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select-field
                        name="timezone"
                        label="Timezone"
                        required
                        :selected="old('timezone', $school->timezone)"
                        :options="collect($timezones)->mapWithKeys(fn ($tz) => [$tz => $tz])->all()"
                    />
                    <x-text-field name="current_session" label="Current Academic Session" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" :value="old('current_session', $school->current_session)" placeholder="e.g. 2025/2026" helper="Used as the default session across the dashboard." />
                </div>

                {{-- Address and contact, on every plan.

                     These used to live only on the school's public website,
                     which Basic schools do not have - so the two places an
                     address matters most, the letterhead on a result sheet and
                     the back of an ID card, were the two a Basic school could
                     not fill in. --}}
                <div class="border-t border-gray-100 pt-6 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Address &amp; Contact</h2>
                    <p class="field-hint mt-1">
                        Printed as the letterhead on result sheets and on the back of ID cards.
                        @if ($school->website)
                            Leave a field blank to use what your website already says.
                        @endif
                    </p>

                    <div class="mt-4 space-y-2">
                        <div>
                            <label class="field-label" for="contact_address">School Address</label>
                            <textarea
                                id="contact_address"
                                name="contact_address"
                                rows="2"
                                class="mt-1.5 w-full"
                                placeholder="e.g. 12 Excellence Avenue, GRA, Enugu, Enugu State, Nigeria."
                            >{{ old('contact_address', $school->contact_address) }}</textarea>
                            @error('contact_address')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <x-text-field
                                name="contact_phone"
                                label="School Phone"
                                icon="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 006 6l1.5-2 4 1.5v3a1 1 0 01-1 1A15.5 15.5 0 015.5 4.5a1 1 0 011-1z"
                                :value="old('contact_phone', $school->contact_phone)"
                                placeholder="e.g. +234 812 345 6789"
                            />
                            <x-text-field
                                name="contact_email"
                                type="email"
                                label="School Email"
                                icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                                :value="old('contact_email', $school->contact_email)"
                                placeholder="e.g. info@yourschool.edu.ng"
                            />
                        </div>
                    </div>
                </div>

                {{-- The name printed under the Principal's ruled line.

                     The signature itself is not here. School Admin is the
                     Principal, so it is registered against the signed-in
                     account on its own card below - one authoritative
                     signature per school, not a school column and an account
                     record that can disagree. --}}
                <div class="border-t border-gray-100 pt-6 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Principal</h2>
                    <p class="field-hint mt-1">
                        You are your school&rsquo;s Principal on ScholarNest. Register the signature itself
                        under <strong>Principal&rsquo;s Signature</strong> at the foot of this page.
                    </p>

                    <div class="mt-4 sm:max-w-md">
                        <x-text-field
                            name="principal_name"
                            label="Principal's Name"
                            icon="M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-4.4 0-8 2.7-8 6h16c0-3.3-3.6-6-8-6z"
                            :value="old('principal_name', $school->principal_name)"
                            :placeholder="auth()->user()->name"
                            helper="Printed under the signature on report cards and ID cards. Leave blank to use your account name."
                        />
                    </div>
                </div>

                {{-- The two lines of words a school prints on its documents.

                     They are not the same line and must not be typed as one:
                     the motto sits under the school's name, the values run
                     along the foot of a report card. Both lived only on the
                     website record until now, so a Basic school's report cards
                     printed its name twice and no values at all. --}}
                <div class="border-t border-gray-100 pt-6 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Motto &amp; Core Values</h2>
                    <p class="field-hint mt-1">
                        The motto prints under your school's name on result letterheads and ID cards.
                        The core values run along the foot of every report card.
                        @if ($school->website)
                            Leave a field blank to use what your website already says.
                        @endif
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <x-text-field
                            name="motto"
                            label="School Motto"
                            icon="M7 8h10M7 12h6M5 4h14a1 1 0 011 1v11a1 1 0 01-1 1H9l-4 3.5V5a1 1 0 011-1z"
                            :value="old('motto', $school->motto)"
                            placeholder="e.g. Raising Excellence, Building Leaders"
                        />
                        <x-text-field
                            name="core_values"
                            label="Core Values"
                            icon="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9z"
                            :value="old('core_values', $school->core_values)"
                            placeholder="e.g. Discipline &bull; Knowledge &bull; Character &bull; Excellence"
                        />
                    </div>
                </div>

                {{-- Social handles, on every plan.

                     Facebook, X and Instagram already existed - on the website
                     record, which Basic schools do not have - and there was
                     nowhere at all for TikTok, WhatsApp, YouTube or LinkedIn.
                     A school fills these in once here and they appear wherever
                     the application shows where to find it. --}}
                <div class="border-t border-gray-100 pt-6 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Social Handles</h2>
                    <p class="field-hint mt-1">
                        Leave any blank. You can paste a full link or just the handle &mdash;
                        &ldquo;facebook.com/yourschool&rdquo; works.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ([
                            ['facebook_url', 'Facebook', 'fa-brands fa-facebook-f', 'facebook.com/yourschool'],
                            ['instagram_url', 'Instagram', 'fa-brands fa-instagram', 'instagram.com/yourschool'],
                            ['whatsapp_number', 'WhatsApp Number', 'fa-brands fa-whatsapp', 'e.g. 08031234567'],
                            ['tiktok_url', 'TikTok', 'fa-brands fa-tiktok', 'tiktok.com/@yourschool'],
                            ['twitter_url', 'X (Twitter)', 'fa-brands fa-x-twitter', 'x.com/yourschool'],
                            ['youtube_url', 'YouTube', 'fa-brands fa-youtube', 'youtube.com/@yourschool'],
                            ['linkedin_url', 'LinkedIn', 'fa-brands fa-linkedin-in', 'linkedin.com/company/yourschool'],
                        ] as [$field, $label, $icon, $placeholder])
                            <div>
                                <label class="field-label flex items-center gap-1.5" for="{{ $field }}">
                                    <i class="{{ $icon }} fa-fw text-[12px] text-primary-600 dark:text-primary-300"></i>
                                    {{ $label }}
                                </label>
                                <input
                                    id="{{ $field }}"
                                    name="{{ $field }}"
                                    type="text"
                                    value="{{ old($field, $school->{$field}) }}"
                                    placeholder="{{ $placeholder }}"
                                    class="mt-1.5 w-full"
                                >
                                @error($field)<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>

                    @if ($whatsappLink = \App\Support\SchoolSocialLinks::whatsappUrl($school->whatsapp_number))
                        <p class="field-hint mt-2">
                            Your WhatsApp number links to <span class="font-semibold">{{ $whatsappLink }}</span>.
                        </p>
                    @endif
                </div>

                {{-- No longer a choice. A grade somebody could type is a grade
                     that can disagree with the marks it came from, and a report
                     card's grades should mean the same thing on every card a
                     school issues. What a school does control is the scale
                     itself - its grade bands - which is what this points at. --}}
                <div class="flex items-start gap-2 rounded-[8px] border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                    <i class="fa-solid fa-circle-check mt-0.5 text-[12px] text-green-600 dark:text-green-400"></i>
                    <span class="text-gray-700 dark:text-gray-200">
                        Automatic Grading
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            Grades and remarks are worked out from each student's Test and Exam scores using
                            <a href="{{ route('academics.index') }}" class="font-semibold text-primary-600 hover:underline">your school's own grading bands</a>.
                            They are never typed in, so a grade can never disagree with the marks behind it.
                        </span>
                    </span>
                </div>

                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Identification Numbers</h3>
                    <p class="field-hint mt-0.5">Configure a school code and let ScholarNest generate unique Admission Numbers and Staff IDs automatically instead of typing them by hand.</p>

                    <div class="mt-4 space-y-4">
                        <x-text-field name="school_code" label="School Code" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" :value="old('school_code', $school->school_code)" placeholder="e.g. MIS" helper="Used as the prefix for every generated Admission Number and Staff ID." />

                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="auto_generate_admission_numbers" value="1" {{ old('auto_generate_admission_numbers', $school->auto_generate_admission_numbers) ? 'checked' : '' }} class="mt-0.5 w-4 text-blue-600">
                            <span>
                                Automatically Generate Admission Numbers
                                <span class="block text-xs text-gray-500 dark:text-gray-400">e.g. {{ $school->school_code ?: 'MIS' }}-{{ $school->current_session ?: '2025/2026' }}-PRY-{{ str_pad((string) $school->next_admission_sequence, 3, '0', STR_PAD_LEFT) }}. Once on, the field is generated for review on the student form and can't be hand-typed.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="auto_generate_staff_ids" value="1" {{ old('auto_generate_staff_ids', $school->auto_generate_staff_ids) ? 'checked' : '' }} class="mt-0.5 w-4 text-blue-600">
                            <span>
                                Automatically Generate Staff IDs
                                <span class="block text-xs text-gray-500 dark:text-gray-400">e.g. {{ $school->school_code ?: 'MIS' }}-STAFF-{{ str_pad((string) $school->next_staff_sequence, 3, '0', STR_PAD_LEFT) }}. Once on, the field is generated for review on the staff form and can't be hand-typed.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Settings</button>
                </div>
            </form>
        </div>

        {{-- The Principal's signature, which is yours.

             On this platform the School Admin IS the Principal - there is no
             separate Principal account to create and no second signature to
             keep in step. What you draw here is what appears on the
             Principal's line of every report card and ID card your school
             issues, in gold.

             Its own card outside the settings form, because it saves the
             moment you draw it: a signature is not something to register and
             then forget to press Save Settings. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Principal&rsquo;s Signature</h2>
            <p class="field-hint mt-0.5">
                Yours, as {{ $school->name }}&rsquo;s Principal. Printed in gold on the Principal&rsquo;s line of
                every report card and ID card, and on any other document your school issues that needs it.
                Class teachers register their own signature from their portal.
            </p>

            <div class="mt-4">
                <x-signature-pad
                    :action="route('signature.store')"
                    :destroy-action="route('signature.destroy')"
                    :current="auth()->user()->signatureDataUri()"
                    title="Register Your Signature"
                    description="Draw it once with your finger, a stylus or your mouse. It saves as soon as you press Save Signature, and appears on your documents in gold."
                />
            </div>

            @unless (auth()->user()->signatureDataUri())
                <p class="mt-3 rounded-[8px] bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    No Principal signature registered yet, so the Principal&rsquo;s line prints blank on your
                    documents. Nobody else&rsquo;s signature is ever used in its place.
                </p>
            @endunless

            <p class="field-hint mt-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                The name printed under the line is set as <strong>Principal&rsquo;s Name</strong> in School Profile above.
                Leave it blank there to print your account name, {{ auth()->user()->name }}.
            </p>
        </div>
    </div>
</x-dashboard-layout>
