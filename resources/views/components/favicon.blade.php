@props(['school' => null, 'platformFallback' => true])

@php($icon = \App\Support\Favicon::for($school, $platformFallback))

{{--
    Exactly one icon, chosen in App\Support\Favicon.

    Deliberately not a list of sizes beside it. Offering several lets the
    browser arbitrate, and it used to pick the bundled default over the
    uploaded one - which is why changing the favicon appeared to do nothing.
--}}
@if ($icon)
    <link rel="icon" type="{{ $icon->type }}" href="{{ $icon->href }}">
    <link rel="apple-touch-icon" href="{{ $icon->href }}">
@else
    {{-- A school's public website with no favicon of its own shows none.
         An empty data URI, rather than no tag at all, so the browser does
         not go looking for /favicon.ico and find AkademicNest's. --}}
    <link rel="icon" href="data:,">
@endif
