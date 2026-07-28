<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-[2px] border border-transparent bg-primary-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-primary-600 focus:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:bg-primary-700']) }}>
    {{ $slot }}
</button>
