{{-- The release number, shown in every portal and on the sign-in pages.
     Read from config('app.version'), so a release changes one line. --}}
<small {{ $attributes->merge(['class' => 'block text-[11px] text-gray-400 dark:text-gray-500']) }}>Version {{ config('app.version') }}</small>
