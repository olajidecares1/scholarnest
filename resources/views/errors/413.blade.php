{{-- The request was larger than the server accepts, almost always a photo
     or document straight off a phone.

     Laravel rejects it before a session exists, so this cannot be sent back to
     the form as a validation message; the page is all there is. It says what
     happened in plain words and always offers the way back, where the form is
     waiting. Most large photos never get here: the browser shrinks them before
     sending (see resources/js/image-upload-prep.js). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>That file is too large | {{ config('app.name', 'AkademicNest') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-dvh items-center justify-center bg-[#F1F5FC] px-4 py-10 font-sans text-[#0F2A5C] antialiased">
        <div class="w-full max-w-md rounded-[14px] border border-[#E1E8F2] bg-white p-7 text-center shadow-[0_18px_50px_-24px_rgba(15,42,92,0.35)]">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-[24px] text-amber-600" aria-hidden="true">&#8679;</span>

            <h1 class="mt-4 text-[20px] font-extrabold tracking-tight">That file is too large to upload</h1>

            <p class="mt-2 text-[13px] leading-[1.65] text-[#5B7099]">
                Nothing was saved. Please go back and choose a smaller photo or document.
                On a phone, a photo taken with the app's own <strong>Take Photo</strong> button is always small enough.
            </p>

            <button
                type="button"
                onclick="history.back()"
                class="mt-6 inline-flex h-[40px] items-center justify-center rounded-[8px] bg-primary-500 px-6 text-[13px] font-bold text-white hover:bg-primary-600"
            >
                Go back to the form
            </button>
        </div>
    </body>
</html>
