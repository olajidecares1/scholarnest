<x-super-admin-layout page-title="Add Role" page-subtitle="Create a custom permission set for your team.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="POST" action="{{ route('super-admin.roles.store') }}" class="space-y-2">
                @csrf
                @include('super-admin.roles._form', ['role' => null])

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="btn flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
                    >
                        Create Role
                    </button>
                    <a href="{{ route('super-admin.roles.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-super-admin-layout>
