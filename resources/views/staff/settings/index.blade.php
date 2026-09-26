<x-staff-layout page-title="Settings" page-subtitle="Keep your contact details up to date">
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Your Contact Details</h2>
            <small class="field-hint mt-0.5">
                Yours to keep current &mdash; you are the one who knows when they change.
                Your school is notified so its records stay in step with yours.
            </small>

            <form method="POST" action="{{ route('staff.settings.update-profile', $school) }}" class="mt-4 space-y-2">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-4">
                    @if ($staff->photoUrl())
                        <img src="{{ $staff->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ Str::of($staff->first_name)->substr(0, 1)->upper() }}</span>
                    @endif
                    <small class="field-hint">Your photo can only be changed by the school office.</small>
                </div>

                <x-text-field name="email" type="email" label="Email Address" :value="$staff->email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" />
                <x-text-field name="phone" type="tel" label="Phone Number" :value="$staff->phone" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" />
                <x-textarea-field name="address" label="Address" rows="2" value="{{ $staff->address }}" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" />

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <x-text-field name="emergency_contact_name" label="Emergency Contact Name" :value="$staff->emergency_contact_name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4" />
                    <x-text-field name="emergency_contact_phone" type="tel" label="Emergency Contact Phone" :value="$staff->emergency_contact_phone" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" />
                </div>

                {{-- Named rather than simply absent. A teacher who cannot find
                     where to change their Staff ID should learn that it is not
                     theirs to change, not that the page is missing something. --}}
                <small class="field-hint">
                    Your name, Staff ID and role are the school&rsquo;s records to maintain. Use the request form
                    below if any of them need correcting.
                </small>

                <div class="flex justify-end">
                    <button type="submit" class="btn rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700">Update</button>
                </div>
            </form>
        </div>

        {{-- A teacher's signature, for the results they sign.

             Uploaded by them and nobody else. A signature is the one thing on
             a report card that is supposed to mean a particular person saw it,
             so the school office cannot put one here on their behalf. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Your Signature</h2>
            <small class="field-hint mt-0.5">
                Added automatically to the class teacher&rsquo;s line on every report card for your class &mdash;
                so you sign a set of results once, not one by one.
            </small>

            <div class="mt-4">
                <x-signature-pad
                    :action="route('staff.settings.signature.store', $school)"
                    :destroy-action="route('staff.settings.signature.destroy', $school)"
                    :current="$staff->signatureDataUri()"
                    title="Register Your Signature"
                    description="Draw it once with your finger, a stylus or your mouse. It is saved to your account and to nobody else's."
                />
            </div>

            {{-- The upload stays alongside the pad. A teacher who already has
                 a scan of their signature should not have to redraw it, and a
                 desktop mouse is a poor pen. Both end in the same place. --}}
            <details class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                <summary class="cursor-pointer text-xs font-semibold text-gray-500 dark:text-gray-400">Or upload a photograph or scan instead</summary>

                <form method="POST" action="{{ route('staff.settings.update-signature', $school) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf

                    <input type="file" name="signature" accept=".png,.jpg,.jpeg,.webp" class="w-full text-sm">
                    <small class="field-hint">
                        Sign on white paper and photograph it, or upload a PNG with a transparent background &mdash;
                        that prints best.
                    </small>
                    @error('signature')<p class="field-error">{{ $message }}</p>@enderror

                    <div class="flex justify-end">
                        <button type="submit" class="btn rounded-[8px] border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300">Upload Signature</button>
                    </div>
                </form>
            </details>
        </div>

        {{-- There is no password form here, and no route behind one either.
             The School Admin is the sole authority on credentials: a portal
             that also offered a self-service change would be a second door
             into the same lock, one the school could not see through. --}}
        <div class="flex items-start gap-3 rounded-[10px] border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
            <i class="fa-solid fa-lock mt-0.5 text-gray-400"></i>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Password</h2>
                <small class="field-hint mt-0.5">
                    Passwords are set by your school office. If you need yours changed or have forgotten it,
                    ask them to issue you a new one &mdash; they can do it straight away.
                </small>
            </div>
        </div>

        <x-profile-change-request-card
            :action="route('staff.profile-change-requests.store', $school)"
            :fields="$protectedFields"
            :requests="$changeRequests"
        />
    </div>
</x-staff-layout>
