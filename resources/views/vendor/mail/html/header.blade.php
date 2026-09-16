@props(['url'])
{{-- The top of every email: the logo above the platform name. The image is
     attached to the email itself (App\Services\Mail\MailLogo), so it shows
     without the reader having to allow remote images. Kept flush left:
     indented lines inside a mail component can turn into a code block. --}}
@php
$logo = app(\App\Services\Mail\MailLogo::class)->image();
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($logo)
<img src="cid:{{ \App\Services\Mail\MailLogo::CID }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt="{{ trim(strip_tags($slot)) }}" style="display: block; margin: 0 auto 10px auto; border: 0; outline: none; width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px; max-width: {{ $logo['width'] }}px;">
@endif
{!! $slot !!}
</a>
</td>
</tr>
