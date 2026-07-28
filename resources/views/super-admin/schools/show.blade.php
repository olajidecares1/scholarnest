<x-super-admin-layout :page-title="$school->name" page-subtitle="School profile, billing, and subscription history.">
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $school->name }}</h2>
                    @if ($school->is_active)
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                    @else
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Inactive</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Joined {{ $school->created_at->format('F j, Y') }}</p>
            </div>

            @if ($school->is_active)
                <form method="POST" action="{{ route('super-admin.schools.deactivate', $school) }}" onsubmit="return confirm('Deactivate {{ $school->name }}? Their admins will lose access immediately.');">
                    @csrf
                    <button type="submit" class="rounded-[8px] border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Deactivate School</button>
                </form>
            @else
                <form method="POST" action="{{ route('super-admin.schools.activate', $school) }}">
                    @csrf
                    <button type="submit" class="rounded-[8px] border border-green-300 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/20">Activate School</button>
                </form>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Billing Details</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Contact Name</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_contact_name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Billing Email</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_email ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_phone ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_address ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">School Admins</h3>
                <ul class="mt-3 space-y-3">
                    @forelse ($school->users as $user)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                {{ Str::of($user->name)->substr(0, 1)->upper() }}
                            </span>
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">{{ $user->name }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">No admins found.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Subscription History</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Plan</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Reference</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($school->subscriptions as $subscription)
                            <tr>
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $subscription->plan->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">&#8358;{{ number_format($subscription->amount, 2) }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('subscriptions.confirmation', $subscription) }}" class="text-primary-500 hover:text-primary-600">{{ $subscription->reference }}</a>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $subscription->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $subscription->status->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No subscriptions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('super-admin.schools.index') }}" class="inline-block text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">&larr; Back to Schools</a>
    </div>
</x-super-admin-layout>
