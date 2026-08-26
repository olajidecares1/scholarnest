{{-- A bare field with no label or message around it. Its appearance comes
     entirely from the shared base rules in app.css, so it looks like every
     other field in the project without repeating a single dimension here. --}}
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes }}>
