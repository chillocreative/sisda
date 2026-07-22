<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Penyelesai skop penapis: nama laluan -> skop + kunci yang dibenarkan.
 *
 * Tulen: tiada sesi, tiada permintaan, tiada pangkalan data. Keputusan
 * "kunci mana yang sah untuk skrin ini" boleh diuji secara langsung.
 */
class FilterScopes
{
    /** @return array{scope:string,keys:array<int,string>}|null */
    public static function forRoute(?string $routeName): ?array
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        foreach (config('sticky_filters', []) as $scope => $def) {
            foreach ($def['routes'] ?? [] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return ['scope' => $scope, 'keys' => $def['keys'] ?? []];
                }
            }
        }

        return null;
    }

    public static function sessionKey(string $scope): string
    {
        return "sticky_filters.{$scope}";
    }
}
