@props([
    'notificationsUrl' => null,
    'messagesUrl' => null,
    'unreadNotifications' => null,
    'unreadMessages' => null,
])

{{-- Notifications and messages in the top bar, where a phone app puts them.

     Each is rendered only when its route actually exists for that portal, the
     staff portal has neither, and a bell that goes nowhere is worse than no
     bell. The count is shown only where the application genuinely tracks read
     state; where it does not, the icon appears without a number rather than
     with a made-up one. --}}
@php
    $iconClasses = 'icon-btn icon-btn--neutral';
    $badgeClasses = 'absolute -right-0.5 -top-0.5 flex h-[17px] min-w-[17px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-gray-900';
@endphp

@if ($messagesUrl)
    <a href="{{ $messagesUrl }}" class="{{ $iconClasses }}" data-tooltip="Messages" aria-label="Messages{{ $unreadMessages ? ' ('.$unreadMessages.' unread)' : '' }}">
        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
        @if ($unreadMessages)
            <span class="{{ $badgeClasses }}" aria-hidden="true">{{ $unreadMessages > 99 ? '99+' : $unreadMessages }}</span>
        @endif
    </a>
@endif

@if ($notificationsUrl)
    <a href="{{ $notificationsUrl }}" class="{{ $iconClasses }}" data-tooltip="Notifications" aria-label="Notifications{{ $unreadNotifications ? ' ('.$unreadNotifications.' unread)' : '' }}">
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        @if ($unreadNotifications)
            <span class="{{ $badgeClasses }}" aria-hidden="true">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
        @endif
    </a>
@endif
