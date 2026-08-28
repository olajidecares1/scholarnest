@props([
    'notificationsUrl' => null,
    'messagesUrl' => null,
    'unreadNotifications' => null,
    'unreadMessages' => null,
])

{{-- Notifications and messages in the top bar, where a phone app puts them.

     Each is rendered only when its route actually exists for that portal - the
     staff portal has neither, and a bell that goes nowhere is worse than no
     bell. The count is shown only where the application genuinely tracks read
     state; where it does not, the icon appears without a number rather than
     with a made-up one. --}}
@php
    $iconClasses = 'relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-600 transition-colors duration-200 hover:bg-gray-100 hover:text-primary-600 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-primary-300';
    $badgeClasses = 'absolute -right-0.5 -top-0.5 flex h-[17px] min-w-[17px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-gray-900';
@endphp

@if ($messagesUrl)
    <a href="{{ $messagesUrl }}" class="{{ $iconClasses }}" title="Messages" aria-label="Messages">
        <i class="fa-solid fa-envelope fa-fw text-[17px] leading-none"></i>
        @if ($unreadMessages)
            <span class="{{ $badgeClasses }}">{{ $unreadMessages > 99 ? '99+' : $unreadMessages }}</span>
            <span class="sr-only">{{ $unreadMessages }} unread</span>
        @endif
    </a>
@endif

@if ($notificationsUrl)
    <a href="{{ $notificationsUrl }}" class="{{ $iconClasses }}" title="Notifications" aria-label="Notifications">
        <i class="fa-solid fa-bell fa-fw text-[17px] leading-none"></i>
        @if ($unreadNotifications)
            <span class="{{ $badgeClasses }}">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
            <span class="sr-only">{{ $unreadNotifications }} unread</span>
        @endif
    </a>
@endif
