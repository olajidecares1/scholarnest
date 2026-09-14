<?php

namespace App\Support;

class PageNavigator
{
    /**
     * Resolve the previous/next entries around the current route within an
     * ordered list of top-level nav items, for the mobile/tablet Previous/Next
     * page navigator. Returns all-null when the current route isn't a
     * top-level nav item (e.g. a record's own show page), the navigator
     * simply doesn't render there.
     *
     * @param  list<array{route: string, label: string, url: string}>  $items
     * @return array{prev: array{url: string, label: string}|null, next: array{url: string, label: string}|null, current: string|null}
     */
    public static function resolve(array $items, ?string $currentRouteName): array
    {
        $index = null;

        foreach ($items as $i => $item) {
            if (self::matches($item['route'], $currentRouteName)) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return ['prev' => null, 'next' => null, 'current' => null];
        }

        return [
            'prev' => $index > 0 ? $items[$index - 1] : null,
            'next' => $index < count($items) - 1 ? $items[$index + 1] : null,
            'current' => $items[$index]['label'],
        ];
    }

    private static function matches(string $itemRoute, ?string $currentRouteName): bool
    {
        if ($currentRouteName === null) {
            return false;
        }

        if ($currentRouteName === $itemRoute) {
            return true;
        }

        // Only treat multi-segment section routes (e.g. "student.assignments.index")
        // as matching their own sub-pages ("student.assignments.show"). Two-segment
        // routes (e.g. "student.dashboard") require an exact match, collapsing them
        // to their guard prefix would match every route in the portal.
        if (substr_count($itemRoute, '.') < 2) {
            return false;
        }

        $prefix = str($itemRoute)->beforeLast('.').'.';

        return str_starts_with($currentRouteName, $prefix);
    }
}
