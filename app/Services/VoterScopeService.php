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
 *
 * Peranan yang tidak dikenali GAGAL-TUTUP (sifar baris). Hanya super_admin
 * yang tidak berhad.
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

        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Peranan lain (pengarah_dun, ketua_paca_dun, apa-apa peranan baharu)
        // TIDAK mempunyai menu Laporan langsung, jadi mereka mesti melihat
        // SIFAR baris. Sebelum ini blok ini jatuh melalui ke "tanpa had",
        // yakni data pengundi seluruh negara — kelas pepijat yang sama
        // seperti fall-through DashboardController.
        return $query->whereRaw('1 = 0');
    }
}
