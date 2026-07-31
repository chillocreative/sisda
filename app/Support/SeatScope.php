<?php

namespace App\Support;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat peraturan kebenaran kerusi ditulis.
 *
 * Peranan → kerusi yang boleh disentuh:
 *   super_admin           semua Parlimen + semua DUN
 *   admin                 Parlimen sendiri + setiap DUN di bawahnya
 *   super_user / user     DUN sendiri sahaja
 *   ketua_paca_dun        tiada (peranannya satu menu: PACA)
 *
 * Tidak khusus kepada Scoreboard — Keanggotaan/Borang 14/PACA boleh
 * menerimanya kemudian, tetapi ITU DI LUAR SKOP kerja semasa.
 *
 * allows() dan seats() SENGAJA diterbitkan daripada tangga peranan yang sama.
 * Jika keduanya bercanggah, kerusi yang tidak muncul dalam pemilih boleh
 * ditulis dengan membina permintaan sendiri — kelas IDOR yang dihotfix pada
 * Julai 2026. SeatScopeTest memaku invarian itu.
 */
class SeatScope
{
    public const DUN = 'dun';

    public const PARLIMEN = 'parlimen';

    public static function allows(?User $user, string $type, int $id): bool
    {
        if (! $user || ! $user->isApproved()) {
            return false;
        }
        if (! in_array($type, [self::DUN, self::PARLIMEN], true)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->isKetuaPacaDun()) {
            return false;
        }

        if ($user->isAdmin()) {
            // Nullable lajur: seorang admin tanpa bandar_id mesti TIDAK padan
            // dengan apa-apa, bukan padan-semua.
            if (! $user->bandar_id) {
                return false;
            }

            return $type === self::PARLIMEN
                ? (int) $user->bandar_id === $id
                : Kadun::whereKey($id)->where('bandar_id', $user->bandar_id)->exists();
        }

        if ($user->isSuperUser() || $user->isUser()) {
            return $type === self::DUN
                && $user->kadun_id
                && (int) $user->kadun_id === $id;
        }

        return false; // Peranan tidak dikenali — tolak.
    }

    public static function assert(?User $user, string $type, int $id): void
    {
        abort_unless(self::allows($user, $type, $id), 403, 'Tindakan tidak dibenarkan.');
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    public static function seats(?User $user): array
    {
        if (! $user || ! $user->isApproved() || $user->isKetuaPacaDun()) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return array_merge(
                self::duns(Kadun::query()),
                self::parlimens(Bandar::query()),
            );
        }

        if ($user->isAdmin()) {
            if (! $user->bandar_id) {
                return [];
            }

            return array_merge(
                self::duns(Kadun::where('bandar_id', $user->bandar_id)),
                self::parlimens(Bandar::whereKey($user->bandar_id)),
            );
        }

        if ($user->isSuperUser() || $user->isUser()) {
            if (! $user->kadun_id) {
                return [];
            }

            return self::duns(Kadun::whereKey($user->kadun_id));
        }

        return [];
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    private static function duns(Builder $q): array
    {
        return $q->orderBy('nama')->get(['id', 'nama', 'kod_dun'])
            ->map(fn ($k) => [
                'type' => self::DUN,
                'id' => (int) $k->id,
                'nama' => (string) $k->nama,
                'kod' => $k->kod_dun ? strtoupper($k->kod_dun) : null,
            ])->all();
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    private static function parlimens(Builder $q): array
    {
        return $q->orderBy('nama')->get(['id', 'nama', 'kod_parlimen'])
            ->map(fn ($b) => [
                'type' => self::PARLIMEN,
                'id' => (int) $b->id,
                'nama' => (string) $b->nama,
                'kod' => $b->kod_parlimen ? strtoupper($b->kod_parlimen) : null,
            ])->all();
    }
}
