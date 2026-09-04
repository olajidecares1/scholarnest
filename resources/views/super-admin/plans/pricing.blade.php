<x-super-admin-layout
    page-title="Plan Pricing"
    page-subtitle="What each plan costs. The per-student price is the figure the student-licence system multiplies by for the Basic and Standard plans."
>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300 lg:rounded-[10px]">
            <p class="font-semibold">Changing a price does not affect payments already submitted.</p>
            <p class="mt-1">
                Every top-up records the price it was quoted at, so a school is always charged
                what it was shown when it paid. A new price applies only to requests made after
                the change.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <form
                    method="POST"
                    action="{{ route('super-admin.plans.pricing.update', $plan) }}"
                    class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                >
                    @csrf
                    @method('PUT')

                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ $plan->key->value }}
                        </span>
                    </div>
                    <p class="field-hint mt-1">{{ $plan->tagline }}</p>

                    <div class="mt-4 space-y-3">
                        {{-- Basic AND Standard are sold per student now - Standard's
                             flat ₦200,000 term fee was replaced by a price per pupil.
                             Both sets of fields are shown for every plan so a plan's
                             model can be changed without a code change, but the one
                             that actually applies is highlighted. --}}
                        <div>
                            <label for="pps-{{ $plan->id }}" class="mb-1 block text-xs font-semibold {{ $plan->key->isSoldPerStudent() ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                                Price per student, per term (&#8358;)
                                @if ($plan->key->isSoldPerStudent())
                                    <span class="font-normal text-blue-600 dark:text-blue-400">&mdash; used for student licences</span>
                                @endif
                            </label>
                            <input
                                id="pps-{{ $plan->id }}"
                                type="number"
                                name="price_per_student_per_term"
                                step="0.01"
                                min="0"
                                value="{{ old('price_per_student_per_term', $plan->price_per_student_per_term) }}"
                                class="w-full"
                            >
                        </div>

                        <div>
                            <label for="pm-{{ $plan->id }}" class="mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">Monthly price (&#8358;)</label>
                            <input
                                id="pm-{{ $plan->id }}"
                                type="number"
                                name="price_monthly"
                                step="0.01"
                                min="0"
                                value="{{ old('price_monthly', $plan->price_monthly) }}"
                                class="w-full"
                            >
                        </div>

                        <div>
                            <label for="ppt-{{ $plan->id }}" class="mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">Price per term (&#8358;)</label>
                            <input
                                id="ppt-{{ $plan->id }}"
                                type="number"
                                name="price_per_term"
                                step="0.01"
                                min="0"
                                value="{{ old('price_per_term', $plan->price_per_term) }}"
                                class="w-full"
                            >
                        </div>
                    </div>

                    @error('price_per_student_per_term')
                        <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-4 w-full rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Save {{ $plan->name }} pricing
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-super-admin-layout>
