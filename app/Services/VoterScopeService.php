<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies role-based row visibility to hasil_culaan / data_pengundi
 * queries. Both tables carry free-text `kadun` / `bandar` columns and a
 * `submitted_by` FK, so one rule serves both.
 *
 * Extracted from ReportsController, where it was written out three times.
 *
 * The '__none__' sentinels are load-bearing: a user with no Kadun assigned
 * must match zero rows. Without them the where() collapses to `kadun = null`
 * and, combined with orWhere(submitted_by), quietly widens visibility.
 */
class VoterScopeService
{
    public static function apply(Builder $query, User $user): Builder
    {
        if ($user->isUser() || $user->isSuperUser()) {
            return $query->where(function ($q) use ($user) {
                $q->where('kadun', $user->kadun->nama ?? '__none__')
                  ->orWhere('submitted_by', $user->id);
            });
        }

        if ($user->isAdmin()) {
            return $query->where(function ($q) use ($user) {
                $q->where('bandar', $user->bandar->nama ?? '__none__')
                  ->orWhere('submitted_by', $user->id);
            });
        }

        // super_admin: unrestricted.
        return $query;
    }
}
