<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Under Maintenance - {{ config('app.name', 'ScholarNest') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
        <div class="max-w-md text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.5 3.5l6 6-8.5 8.5-6.5 1 1-6.5 8-8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M12.5 5.5l6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                </svg>
            </span>
            <h1 class="mt-6 text-2xl font-extrabold">We&rsquo;ll be right back</h1>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ $message ?: 'ScholarNest is currently undergoing scheduled maintenance. Please check back shortly.' }}
            </p>
        </div>
    </body>
</html>
